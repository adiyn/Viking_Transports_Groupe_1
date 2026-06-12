<?php
declare(strict_types=1);

/**
 * Page : reservation d'un voyage (priorites 3, 4 et 5).
 * - Mode "ligne"     : voyage sur une ligne complete (client non inscrit).
 * - Mode "partielle" : voyage sur une partie d'une ligne (client non inscrit).
 * - Mode "multi"     : voyage sur plusieurs lignes choisies manuellement (client connecte).
 * Rendu serveur : affiche le formulaire (GET), le récapitulatif avec prix, puis la confirmation (POST).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Inclusion des configurations et des fonctions PDO de l'application */
require_once __DIR__ . '/../config/pdo_agile.php';
require_once __DIR__ . '/../config/param_connexion.php';

define("MOD_BDD", "ORACLE");

if (MOD_BDD == "MYSQL") {
    $db_username = $db_usernameMySQL;
    $db_password = $db_passwordMySQL;
    $db = $dbMySQL;
} else {
    $db_username = $db_usernameOracle;
    $db_password = $db_passwordOracle;
    $db = $dbOracle;
}

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

/* Force le point comme separateur decimal pour cette session Oracle.
   Sinon, selon le NLS du serveur (souvent la virgule en français), l'insertion d'une
   distance a decimales liee en chaine "44.9" provoque ORA-01722 (Nombre non valide). */
if ($conn !== null) {
    $conn->exec("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'");
}

$erreur         = null;
$reservation    = null;
$afficher_recap = false;

/* Le client est-il connecte ? (necessaire pour le mode "multi") */
$estConnecte = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$cliConnecte = $_SESSION['utilisateur'] ?? null;

/* Mode de reservation choisi : ligne (defaut), partielle ou multi */
$modeReservation = isset($_POST['mode_reservation']) ? trim((string) $_POST['mode_reservation']) : 'ligne';
if (!in_array($modeReservation, ['ligne', 'partielle', 'multi'], true)) {
    $modeReservation = 'ligne';
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['prefill']) && isset($_GET['mode'])
    && in_array($_GET['mode'], ['ligne', 'partielle', 'multi'], true)) {
    $modeReservation = trim((string) $_GET['mode']);
}

/* Tableau brut des etapes soumises en mode "multi", utilise pour repeupler le formulaire en cas d'erreur */
$legsPost = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['prefill']) && isset($_GET['legs']) && is_array($_GET['legs'])) {
    $legsPost = $_GET['legs'];
}

/* ===================== FONCTIONS UTILITAIRES ===================== */

/**
 * Cherche la tranche tarifaire correspondant a une distance.
 * @return array{tranche:int, prix:int}|null
 */
function obtenirTarif(array $tarifs, float $distance): ?array
{
    foreach ($tarifs as $t) {
        $max = (float) str_replace(',', '.', (string) ($t['TAR_MAX_DIST'] ?? $t['tar_max_dist']));
        if ($distance <= $max) {
            return [
                'tranche' => (int) ($t['TAR_NUM_TRANCHE'] ?? $t['tar_num_tranche']),
                'prix'    => (int) ($t['TAR_PRIX'] ?? $t['tar_prix']),
            ];
        }
    }
    return null;
}

/* Cherche l'index d'un arret (code insee) dans la liste ordonnee des arrets d'une ligne */
function trouverIndexArret(array $stops, string $code): ?int
{
    foreach ($stops as $i => $s) {
        if ($s['code'] === $code) {
            return $i;
        }
    }
    return null;
}

/**
 * Construit une etape (depart, arrivee, distance et duree cumulees) entre deux arrets
 * d'une meme ligne, ou null si le trajet est invalide. L'heure de depart n'est PAS
 * imposee ici : elle est choisie par l'utilisateur ; l'heure d'arrivee se calcule ensuite
 * via ajouterMinutes(heure_depart, duree).
 * @return array{depart_code:string, depart_nom:string, arrivee_code:string, arrivee_nom:string, distance:float, duree:?int}|null
 */
function calculerEtape(array $stops, string $depCode, string $arrCode): ?array
{
    $d = trouverIndexArret($stops, $depCode);
    $a = trouverIndexArret($stops, $arrCode);
    // On ne peut pas partir du terminus (heure de depart null) ni arriver avant de partir.
    if ($d === null || $a === null || $a <= $d || $stops[$d]['heure'] === null) {
        return null;
    }

    $distance = 0.0;
    $duree    = 0;
    $dureeConnue = true;
    for ($i = $d; $i < $a; $i++) {
        $distance += (float) $stops[$i]['distance_suivant'];
        if ($stops[$i]['duree_suivant'] === null) {
            $dureeConnue = false;
        } else {
            $duree += (int) $stops[$i]['duree_suivant'];
        }
    }

    return [
        'depart_code'  => $stops[$d]['code'],
        'depart_nom'   => $stops[$d]['nom'],
        'arrivee_code' => $stops[$a]['code'],
        'arrivee_nom'  => $stops[$a]['nom'],
        'distance'     => $distance,
        'duree'        => $dureeConnue ? $duree : null,
    ];
}

/* Ajoute un nombre de minutes a une heure "HH:MM" et renvoie "HH:MM" (ou null si entree invalide) */
function ajouterMinutes(?string $heure, ?int $minutes): ?string
{
    if ($heure === null || $minutes === null) {
        return null;
    }
    return date('H:i', strtotime($heure) + $minutes * 60);
}

/* Calcule l'heure d'arrivee reelle via CTE Oracle pour un trajet partiel.
   Retourne "HH:MM" si trouve, null sinon (fallback sur ajouterMinutes). */
function calculerHeureArriveeReelle(PDO $conn, string $ligNum, string $depCode, string $arrCode, string $resHeure): ?string
{
    $sql = "
        WITH trip_chain(LIG_CUR, HEURE_DEP, HEURE_CUR, SUIVANT_CUR, DUREE_CUR) AS (
            SELECT TRIM(LIG_NUM), NOE_HEURE_PASSAGE, NOE_HEURE_PASSAGE,
                   COM_CODE_INSEE_SUIVANT, NOE_DUREE_PROCHAIN
            FROM VIK_NOEUD
            WHERE COM_CODE_INSEE_ARRET = :dep
              AND TRIM(LIG_NUM) = :lig
            UNION ALL
            SELECT t.LIG_CUR, t.HEURE_DEP, n.NOE_HEURE_PASSAGE,
                   n.COM_CODE_INSEE_SUIVANT, n.NOE_DUREE_PROCHAIN
            FROM VIK_NOEUD n
            JOIN trip_chain t ON TRIM(n.LIG_NUM) = t.LIG_CUR
                AND n.COM_CODE_INSEE_ARRET = t.SUIVANT_CUR
                AND n.NOE_HEURE_PASSAGE = t.HEURE_CUR + t.DUREE_CUR / 1440
        )
        SELECT TO_CHAR(HEURE_CUR + DUREE_CUR / 1440, 'HH24:MI') AS HEURE_ARRIVEE
        FROM trip_chain
        WHERE SUIVANT_CUR = :arr
          AND TO_CHAR(HEURE_DEP, 'HH24:MI') = :heure
        FETCH FIRST 1 ROWS ONLY
    ";
    $st = preparerRequetePDO($conn, $sql);
    ajouterParamPDO($st, ':dep',   $depCode,  'texte');
    ajouterParamPDO($st, ':arr',   $arrCode,  'texte');
    ajouterParamPDO($st, ':lig',   $ligNum,   'texte');
    ajouterParamPDO($st, ':heure', $resHeure, 'texte');
    $tab = [];
    LireDonneesPDOPreparee($st, $tab);
    if (!empty($tab)) {
        return $tab[0]['HEURE_ARRIVEE'] ?? $tab[0]['heure_arrivee'] ?? null;
    }
    return null;
}

/* Prochain CLI_NUM libre */
function obtenirProchainCliNum(PDO $conn): int
{
    $st = preparerRequetePDO($conn, "SELECT NVL(MAX(cli_num), 0) + 1 AS next_cli FROM VIK_CLIENT");
    $tab = [];
    LireDonneesPDOPreparee($st, $tab);
    return (int) ($tab[0]['NEXT_CLI'] ?? $tab[0]['next_cli'] ?? 1);
}

/* Prochain RES_NUM libre pour un client donne */
function obtenirProchainResNum(PDO $conn, int $cliNum): int
{
    $st = preparerRequetePDO($conn, "SELECT NVL(MAX(res_num), 0) + 1 AS next_num FROM vik_reservation WHERE cli_num = :cli");
    ajouterParamPDO($st, ':cli', $cliNum, 'nombre');
    $tab = [];
    LireDonneesPDOPreparee($st, $tab);
    return (int) ($tab[0]['NEXT_NUM'] ?? $tab[0]['next_num'] ?? 1);
}

/* Cree un client "non inscrit" (TYP_NUM = 3, comme dans la version d'origine) et renvoie son CLI_NUM */
function creerClientNonInscrit(PDO $conn, string $nom, string $prenom, string $tel, string $mail): ?int
{
    $cliNum = obtenirProchainCliNum($conn);

    $sql = "INSERT INTO VIK_CLIENT (cli_num, typ_num, cli_nom, cli_prenom, cli_telephone, cli_courriel)
            VALUES (:cli, 3, :nom, :prenom, :tel, :mail)";
    $st = preparerRequetePDO($conn, $sql);
    ajouterParamPDO($st, ':cli', $cliNum, 'nombre');
    ajouterParamPDO($st, ':nom', $nom, 'texte');
    ajouterParamPDO($st, ':prenom', $prenom, 'texte');
    ajouterParamPDO($st, ':tel', $tel, 'texte');
    ajouterParamPDO($st, ':mail', $mail, 'texte');

    // NB : ajouterParamPDO lie le parametre en PARAM_INPUT_OUTPUT ; apres execute(),
    // PDO_OCI reecrit $cliNum en string. On recaste donc explicitement en int.
    return majDonneesPrepareesPDO($st) ? (int) $cliNum : null;
}

/**
 * Renvoie le CLI_NUM a utiliser pour la reservation :
 * - client connecte : son propre compte (aucune saisie de coordonnees) ;
 * - sinon : creation d'un client non inscrit a partir des coordonnees saisies.
 */
function resoudreClientReservation(PDO $conn, bool $estConnecte, ?array $cliConnecte, string $nom, string $prenom, string $tel, string $mail): ?int
{
    if ($estConnecte && $cliConnecte !== null) {
        return (int) $cliConnecte['num'];
    }
    return creerClientNonInscrit($conn, $nom, $prenom, $tel, $mail);
}

/* Points de fidelite gagnes pour un voyage : 1 point par tranche de 10 km parcourus
   (ex. 247,7 km -> 24 points, cf. scenario du sujet). */
function calculerPoints(float $distance): int
{
    return (int) floor($distance / 10);
}

/**
 * Met a jour les points du client apres une reservation :
 * - solde courant (cli_nb_points_ec) : + points gagnes - points depenses ;
 * - total cumule (cli_nb_points_tot) : + points gagnes seulement (jamais diminue).
 */
function ajusterPointsClient(PDO $conn, int $cliNum, int $gagnes, int $depenses): bool
{
    if ($gagnes <= 0 && $depenses <= 0) {
        return true;
    }
    $variationEc = $gagnes - $depenses;
    $sql = "UPDATE VIK_CLIENT
               SET cli_nb_points_ec  = NVL(cli_nb_points_ec, 0)  + :ec,
                   cli_nb_points_tot = NVL(cli_nb_points_tot, 0) + :tot
             WHERE cli_num = :cli";
    $st = preparerRequetePDO($conn, $sql);
    ajouterParamPDO($st, ':ec',  $variationEc, 'nombre');
    ajouterParamPDO($st, ':tot', $gagnes, 'nombre');
    ajouterParamPDO($st, ':cli', $cliNum, 'nombre');
    return majDonneesPrepareesPDO($st);
}

/**
 * Valide le palier de reduction choisi et calcule le prix final.
 * @return array{points:int, valeur:int, prix:int}|null  null si le choix est invalide (palier inconnu ou solde insuffisant).
 */
function appliquerReduction(array $reductions, int $prixBase, int $pointsUtilises, int $solde): ?array
{
    if ($pointsUtilises <= 0) {
        return ['points' => 0, 'valeur' => 0, 'prix' => $prixBase];
    }
    foreach ($reductions as $r) {
        $rp = (int) ($r['RED_NB_POINTS'] ?? $r['red_nb_points']);
        $rv = (int) ($r['RED_VALEUR'] ?? $r['red_valeur']);
        if ($rp === $pointsUtilises) {
            if ($rp > $solde) {
                return null; // pas assez de points
            }
            return ['points' => $rp, 'valeur' => $rv, 'prix' => max(0, $prixBase - $rv)];
        }
    }
    return null; // palier inconnu
}

/**
 * Enregistre une reservation et ses etapes (une ou plusieurs) pour un client existant.
 * $etapes est un tableau de tableaux issus de calculerEtape() complete par 'lig_num'.
 * Renvoie le RES_NUM cree, ou null en cas d'echec.
 */
function enregistrerReservation(PDO $conn, int $cliNum, int $tranche, string $resDate, int $points, int $prix, array $etapes): ?int
{
    $resNum = obtenirProchainResNum($conn, $cliNum);

    $sqlRes = "INSERT INTO vik_reservation (cli_num, res_num, tar_num_tranche, res_date, res_nb_points, res_prix_tot)
               VALUES (:cli, :res, :tar, TO_DATE(:res_date, 'YYYY-MM-DD'), :pts, :prix)";
    $stRes = preparerRequetePDO($conn, $sqlRes);
    ajouterParamPDO($stRes, ':cli',  $cliNum, 'nombre');
    ajouterParamPDO($stRes, ':res',  $resNum, 'nombre');
    ajouterParamPDO($stRes, ':tar',  $tranche, 'nombre');
    ajouterParamPDO($stRes, ':res_date', $resDate, 'texte');
    ajouterParamPDO($stRes, ':pts',  $points, 'nombre');
    ajouterParamPDO($stRes, ':prix', $prix, 'nombre');

    if (!majDonneesPrepareesPDO($stRes)) {
        return null;
    }

    foreach ($etapes as $etape) {
        $sqlEta = "INSERT INTO VIK_ETAPE (lig_num, cli_num, res_num, com_code_insee_depart, com_code_insee_arrivee, eta_distance, eta_heure)
                   VALUES (:lig, :cli, :res, :dep, :arr, :dist, TO_DATE(:heure, 'HH24:MI'))";
        $stEta = preparerRequetePDO($conn, $sqlEta);
        ajouterParamPDO($stEta, ':lig',   $etape['lig_num'], 'texte');
        ajouterParamPDO($stEta, ':cli',   $cliNum, 'nombre');
        ajouterParamPDO($stEta, ':res',   $resNum, 'nombre');
        ajouterParamPDO($stEta, ':dep',   $etape['depart_code'], 'texte');
        ajouterParamPDO($stEta, ':arr',   $etape['arrivee_code'], 'texte');
        $dist = round((float) $etape['distance'], 1);
        ajouterParamPDO($stEta, ':dist',  $dist, 'texte');
        ajouterParamPDO($stEta, ':heure', $etape['heure'], 'texte');

        if (!majDonneesPrepareesPDO($stEta)) {
            return null;
        }
    }

    // Meme remarque que dans creerClientNonInscrit : $resNum a pu etre reecrit en string par PDO_OCI.
    return (int) $resNum;
}

/* ===================== DONNEES DE REFERENCE (formulaires) ===================== */

/* Liste des lignes pour les formulaires */
$lignes = [];
$sqlLignes = "
    SELECT TRIM(l.lig_num) AS lig_num, cd.com_nom AS depart, ct.com_nom AS arrivee
    FROM vik_ligne l
    JOIN vik_commune cd ON cd.com_code_insee = l.com_code_insee_debu
    JOIN vik_commune ct ON ct.com_code_insee = l.com_code_insee_term
    ORDER BY LENGTH(TRIM(l.lig_num)), TRIM(l.lig_num)";

LireDonneesPDO3($conn, $sqlLignes, $lignes);

/* Tranches tarifaires (pour calculer le prix d'une distance partielle ou cumulee) */
$tarifs = [];
LireDonneesPDO3($conn, "SELECT tar_num_tranche, tar_max_dist, tar_prix FROM vik_tarif ORDER BY tar_num_tranche", $tarifs);

/* Bareme des reductions fidelite (paliers de points -> euros de remise) */
$reductions = [];
LireDonneesPDO3($conn, "SELECT red_nb_points, red_valeur FROM vik_reduction ORDER BY red_nb_points", $reductions);

/* Solde de points du client connecte (pour proposer les reductions qu'il peut s'offrir) */
$soldePoints = 0;
if ($estConnecte && $cliConnecte !== null) {
    $stSolde = preparerRequetePDO($conn, "SELECT NVL(cli_nb_points_ec, 0) AS solde FROM vik_client WHERE cli_num = :cli");
    $cliNumSolde = (int) $cliConnecte['num'];
    ajouterParamPDO($stSolde, ':cli', $cliNumSolde, 'nombre');
    $tabSolde = [];
    LireDonneesPDOPreparee($stSolde, $tabSolde);
    $soldePoints = (int) ($tabSolde[0]['SOLDE'] ?? $tabSolde[0]['solde'] ?? 0);
}

/* Horaires de depart en tete de ligne, pour le mode "ligne complete" */
$sqlHoraires = "
    SELECT DISTINCT TRIM(n.lig_num) AS lig_num, TO_CHAR(n.noe_heure_passage, 'HH24:MI') AS heure
    FROM vik_noeud n
    JOIN vik_ligne l ON TRIM(l.lig_num) = TRIM(n.lig_num)
    WHERE n.com_code_insee_arret = l.com_code_insee_debu
    ORDER BY heure";

$horairesRaw = [];
LireDonneesPDO3($conn, $sqlHoraires, $horairesRaw);

$horairesParLigne = [];
foreach ($horairesRaw as $h) {
    $l_id = $h['LIG_NUM'] ?? $h['lig_num'];
    $h_val = $h['HEURE'] ?? $h['heure'];
    $horairesParLigne[$l_id][] = $h_val;
}

/* Codes des communes de depart / terminus de chaque ligne (pour reconstruire le trace) */
$lignesCodes = [];
LireDonneesPDO3($conn, "SELECT TRIM(lig_num) AS lig_num, com_code_insee_debu, com_code_insee_term FROM vik_ligne", $lignesCodes);

$ligneDebut = [];
$ligneTerm  = [];
foreach ($lignesCodes as $lc) {
    $lig = trim((string) ($lc['LIG_NUM'] ?? $lc['lig_num']));
    $ligneDebut[$lig] = trim((string) ($lc['COM_CODE_INSEE_DEBU'] ?? $lc['com_code_insee_debu']));
    $ligneTerm[$lig]  = trim((string) ($lc['COM_CODE_INSEE_TERM'] ?? $lc['com_code_insee_term']));
}

/* Arrets de chaque ligne.
   IMPORTANT : dans vik_noeud, chaque troncon (arret -> suivant) apparait PLUSIEURS fois,
   une fois par horaire de passage de la journee. La geographie (suivant, distance, duree)
   est identique d'un passage a l'autre. On dedoublonne donc par (ligne, arret, suivant)
   pour ne PAS compter plusieurs fois la meme distance (sinon le total km est gonfle). */
$sqlNoeuds = "
    SELECT TRIM(n.lig_num) AS lig_num,
           n.com_code_insee_arret AS arret_code,
           ca.com_nom AS arret_nom,
           n.com_code_insee_suivant AS suivant_code,
           cs.com_nom AS suivant_nom,
           TO_CHAR(MIN(n.noe_heure_passage), 'HH24:MI') AS heure,
           MAX(n.noe_distance_prochain) AS distance,
           MAX(n.noe_duree_prochain) AS duree
    FROM vik_noeud n
    JOIN vik_commune ca ON ca.com_code_insee = n.com_code_insee_arret
    JOIN vik_commune cs ON cs.com_code_insee = n.com_code_insee_suivant
    GROUP BY TRIM(n.lig_num), n.com_code_insee_arret, ca.com_nom, n.com_code_insee_suivant, cs.com_nom";

$noeudsRaw = [];
LireDonneesPDO3($conn, $sqlNoeuds, $noeudsRaw);

/* Index : pour chaque ligne, arret -> { suivant, distance, duree, heure de passage } */
$arcsParLigne = [];
$nomCommune   = [];
foreach ($noeudsRaw as $n) {
    $lig     = trim((string) ($n['LIG_NUM'] ?? $n['lig_num']));
    $arret   = trim((string) ($n['ARRET_CODE'] ?? $n['arret_code']));
    $suivant = trim((string) ($n['SUIVANT_CODE'] ?? $n['suivant_code']));

    $nomCommune[$arret]   = $n['ARRET_NOM'] ?? $n['arret_nom'];
    $nomCommune[$suivant] = $n['SUIVANT_NOM'] ?? $n['suivant_nom'];

    $arcsParLigne[$lig][$arret] = [
        'suivant'  => $suivant,
        'distance' => (float) str_replace(',', '.', (string) ($n['DISTANCE'] ?? $n['distance'])),
        'duree'    => ($n['DUREE'] ?? $n['duree']) !== null ? (int) ($n['DUREE'] ?? $n['duree']) : null,
        'heure'    => $n['HEURE'] ?? $n['heure'],
    ];
}

/* Tous les horaires de passage (departs) possibles a chaque arret d'une ligne,
   pour proposer le choix de l'horaire de depart en plus de l'arret. */
$sqlHorairesArret = "
    SELECT TRIM(lig_num) AS lig_num,
           com_code_insee_arret AS arret_code,
           TO_CHAR(noe_heure_passage, 'HH24:MI') AS heure
    FROM vik_noeud
    GROUP BY TRIM(lig_num), com_code_insee_arret, TO_CHAR(noe_heure_passage, 'HH24:MI')
    ORDER BY 1, 2, 3";

$horairesArretRaw = [];
LireDonneesPDO3($conn, $sqlHorairesArret, $horairesArretRaw);

$horairesArret = [];   // $horairesArret[ligne][arret] = [ "07:00", "10:00", ... ]
foreach ($horairesArretRaw as $hr) {
    $lig   = trim((string) ($hr['LIG_NUM'] ?? $hr['lig_num']));
    $arret = trim((string) ($hr['ARRET_CODE'] ?? $hr['arret_code']));
    $horairesArret[$lig][$arret][] = $hr['HEURE'] ?? $hr['heure'];
}

/* Reconstruction du trace geographique de chaque ligne : on part de la commune de depart
   et on suit les pointeurs "suivant" jusqu'au terminus. Chaque arret n'apparait qu'une fois. */
$stopsParLigne = [];
foreach ($arcsParLigne as $lig => $arcs) {
    if (!isset($ligneDebut[$lig])) {
        continue;
    }

    $courant = $ligneDebut[$lig];
    $stops   = [];
    $vus     = [];

    while (isset($arcs[$courant]) && !isset($vus[$courant])) {
        $vus[$courant] = true;
        $arc = $arcs[$courant];
        $stops[] = [
            'code'             => $courant,
            'nom'              => $nomCommune[$courant] ?? $courant,
            'heure'            => $arc['heure'],   // 1er passage (sert juste a savoir que l'arret est un depart possible)
            'horaires'         => $horairesArret[$lig][$courant] ?? [],  // tous les horaires de depart a cet arret
            'distance_suivant' => $arc['distance'],
            'duree_suivant'    => $arc['duree'],
        ];
        $courant = $arc['suivant'];
    }

    /* Terminus : pas de depart possible (aucun horaire de depart). */
    $stops[] = [
        'code'             => $courant,
        'nom'              => $nomCommune[$courant] ?? ($ligneTerm[$lig] ?? $courant),
        'heure'            => null,
        'horaires'         => [],
        'distance_suivant' => null,
        'duree_suivant'    => null,
    ];

    $stopsParLigne[$lig] = $stops;
}

/* Requete commune : distance, tarif, communes d'une ligne (mode "ligne complete"). */
$sqlInfo = "
    SELECT l.lig_num,
           l.com_code_insee_debu,
           l.com_code_insee_term,
           cd.com_nom AS depart,
           ct.com_nom AS arrivee,
           d.distance_totale,
           t.tar_num_tranche,
           t.tar_prix
    FROM vik_ligne l
    JOIN vik_commune cd ON cd.com_code_insee = l.com_code_insee_debu
    JOIN vik_commune ct ON ct.com_code_insee = l.com_code_insee_term
    JOIN (
        SELECT TRIM(lig_num) AS lig_num, SUM(dist) AS distance_totale
        FROM (
            SELECT lig_num, com_code_insee_arret, com_code_insee_suivant,
                   MAX(noe_distance_prochain) AS dist
            FROM vik_noeud
            WHERE TRIM(lig_num) = :lig_num
            GROUP BY lig_num, com_code_insee_arret, com_code_insee_suivant
        )
        GROUP BY TRIM(lig_num)
    ) d ON TRIM(l.lig_num) = d.lig_num
    JOIN vik_tarif t
      ON t.tar_num_tranche = (
            SELECT MIN(tar_num_tranche)
            FROM vik_tarif
            WHERE tar_max_dist >= d.distance_totale
         )
    WHERE TRIM(l.lig_num) = :lig_num";

/* ===================== TRAITEMENT DE LA RESERVATION (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ----------------------------------------------------------------
     * MODE "ligne" : voyage sur une ligne complete (priorite 3)
     * ---------------------------------------------------------------- */
    if ($modeReservation === 'ligne') {

        $ligNum    = isset($_POST['lig_num']) ? trim((string) $_POST['lig_num']) : '';
        $resDate   = isset($_POST['res_date']) ? trim((string) $_POST['res_date']) : '';
        $resHeure  = isset($_POST['res_heure']) ? trim((string) $_POST['res_heure']) : '';

        $resNom    = isset($_POST['res_nom']) ? trim((string) $_POST['res_nom']) : '';
        $resPrenom = isset($_POST['res_prenom']) ? trim((string) $_POST['res_prenom']) : '';
        $resTel    = isset($_POST['res_tel']) ? trim((string) $_POST['res_tel']) : '';
        $resMail   = isset($_POST['res_mail']) ? trim((string) $_POST['res_mail']) : '';

        $coordOk = $estConnecte || ($resNom !== '' && $resPrenom !== '' && $resTel !== '' && $resMail !== '');
        if ($ligNum === '' || $resDate === '' || $resHeure === '' || !$coordOk) {
            $erreur = 'Veuillez remplir tous les champs (ligne, date, horaire' . ($estConnecte ? '' : ' et coordonnées') . ').';
        } else {
            // Validation de la date et de l'heure : on ne peut pas réserver dans le passé
            date_default_timezone_set('Europe/Paris');
            if (strtotime($resDate . ' ' . $resHeure) < time()) {
                $erreur = "La date et l'heure de réservation ne peuvent pas être dans le passé.";
            } else {
                $stInfo = preparerRequetePDO($conn, $sqlInfo);
                ajouterParamPDO($stInfo, ':lig_num', $ligNum, 'texte');

                $tabInfo = [];
                LireDonneesPDOPreparee($stInfo, $tabInfo);
                $info = !empty($tabInfo) ? $tabInfo[0] : false;

                if ($info === false) {
                    $erreur = 'Ligne inconnue ou tarif introuvable pour cette distance.';
                } else {
                    $debu     = $info['COM_CODE_INSEE_DEBU'] ?? $info['com_code_insee_debu'];
                    $term     = $info['COM_CODE_INSEE_TERM'] ?? $info['com_code_insee_term'];
                    $distance = (float) ($info['DISTANCE_TOTALE'] ?? $info['distance_totale']);
                    $tranche  = (int) ($info['TAR_NUM_TRANCHE'] ?? $info['tar_num_tranche']);
                    $prix     = (int) ($info['TAR_PRIX'] ?? $info['tar_prix']);
                    $depart   = $info['DEPART'] ?? $info['depart'];
                    $arrivee  = $info['ARRIVEE'] ?? $info['arrivee'];

                    // Reduction fidelite (points depenses) : uniquement pour un client connecte
                    $pointsUtilises = $estConnecte ? (int) ($_POST['points_utilises'] ?? 0) : 0;
                    $reduc = appliquerReduction($reductions, $prix, $pointsUtilises, $soldePoints);

                    if ($reduc === null) {
                        $erreur = "Réduction invalide ou points de fidélité insuffisants.";
                    } elseif (isset($_POST['confirmation_finale'])) {
                        $conn->beginTransaction();

                        try {
                            $cliNum = resoudreClientReservation($conn, $estConnecte, $cliConnecte, $resNom, $resPrenom, $resTel, $resMail);
                            // Points gagnes (fonction 11) : uniquement pour un client connecte (fidelise)
                            $points = $estConnecte ? calculerPoints($distance) : 0;
                            $resNum = $cliNum !== null
                                ? enregistrerReservation($conn, $cliNum, $tranche, $resDate, $points, $reduc['prix'], [[
                                    'lig_num'      => $ligNum,
                                    'depart_code'  => $debu,
                                    'arrivee_code' => $term,
                                    'distance'     => $distance,
                                    'heure'        => $resHeure,
                                  ]])
                                : null;
                            $okPoints = ($resNum !== null) ? ajusterPointsClient($conn, $cliNum, $points, $reduc['points']) : false;

                            if ($cliNum !== null && $resNum !== null && $okPoints) {
                                $conn->commit();
                                $reservation = [
                                    'reference' => 'VT-' . date('Y') . '-' . str_pad((string) $resNum, 5, '0', STR_PAD_LEFT),
                                    'trajets'   => [[
                                        'lig_num'       => $ligNum,
                                        'depart'        => $depart,
                                        'arrivee'       => $arrivee,
                                        'heure'         => $resHeure,
                                        'heure_arrivee' => null,
                                    ]],
                                    'prix'           => $reduc['prix'],
                                    'reduction'      => $reduc['valeur'],
                                    'points_utilises'=> $reduc['points'],
                                    'points'         => $points,
                                    'date'           => date('d/m/Y', strtotime($resDate)),
                                ];
                            } else {
                                $conn->rollBack();
                                $erreur = "La réservation n'a pas pu être enregistrée. Veuillez réessayer.";
                            }
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $erreur = "Une erreur technique est survenue : " . $e->getMessage();
                        }
                    } else {
                        $afficher_recap = true;
                    }
                }
            }
        }

    /* ----------------------------------------------------------------
     * MODE "partielle" : voyage sur une partie d'une ligne (priorite 4)
     * ---------------------------------------------------------------- */
    } elseif ($modeReservation === 'partielle') {

        $ligNum       = isset($_POST['lig_num']) ? trim((string) $_POST['lig_num']) : '';
        $arretDepart  = isset($_POST['arret_depart']) ? trim((string) $_POST['arret_depart']) : '';
        $arretArrivee = isset($_POST['arret_arrivee']) ? trim((string) $_POST['arret_arrivee']) : '';
        $resHeure     = isset($_POST['res_heure']) ? trim((string) $_POST['res_heure']) : '';
        $resDate      = isset($_POST['res_date']) ? trim((string) $_POST['res_date']) : '';

        $resNom    = isset($_POST['res_nom']) ? trim((string) $_POST['res_nom']) : '';
        $resPrenom = isset($_POST['res_prenom']) ? trim((string) $_POST['res_prenom']) : '';
        $resTel    = isset($_POST['res_tel']) ? trim((string) $_POST['res_tel']) : '';
        $resMail   = isset($_POST['res_mail']) ? trim((string) $_POST['res_mail']) : '';

        $coordOk = $estConnecte || ($resNom !== '' && $resPrenom !== '' && $resTel !== '' && $resMail !== '');
        if ($ligNum === '' || $arretDepart === '' || $arretArrivee === '' || $resHeure === '' || $resDate === '' || !$coordOk) {
            $erreur = 'Veuillez remplir tous les champs (ligne, arrêts, horaire, date' . ($estConnecte ? '' : ' et coordonnées') . ').';
        } elseif (!isset($stopsParLigne[$ligNum])) {
            $erreur = 'Ligne inconnue.';
        } else {
            $etape = calculerEtape($stopsParLigne[$ligNum], $arretDepart, $arretArrivee);

            if ($etape === null) {
                $erreur = "L'arrêt d'arrivée doit être desservi après l'arrêt de départ sur cette ligne.";
            } elseif (!in_array($resHeure, $stopsParLigne[$ligNum][trouverIndexArret($stopsParLigne[$ligNum], $arretDepart)]['horaires'], true)) {
                $erreur = "L'horaire de départ choisi n'est pas valable pour cet arrêt.";
            } else {
                // L'heure de depart est celle choisie ; l'heure d'arrivee est calculee via CTE reelle.
                $etape['heure']         = $resHeure;
                $etape['heure_arrivee'] = calculerHeureArriveeReelle($conn, $ligNum, $arretDepart, $arretArrivee, $resHeure)
                                          ?? ajouterMinutes($resHeure, $etape['duree']);

                date_default_timezone_set('Europe/Paris');
                if (strtotime($resDate . ' ' . $resHeure) < time()) {
                    $erreur = "La date et l'heure de réservation ne peuvent pas être dans le passé.";
                } else {
                    $tarif = obtenirTarif($tarifs, $etape['distance']);
                    $pointsUtilises = $estConnecte ? (int) ($_POST['points_utilises'] ?? 0) : 0;
                    $reduc = ($tarif !== null) ? appliquerReduction($reductions, $tarif['prix'], $pointsUtilises, $soldePoints) : null;

                    if ($tarif === null) {
                        $erreur = 'Tarif introuvable pour cette distance.';
                    } elseif ($reduc === null) {
                        $erreur = "Réduction invalide ou points de fidélité insuffisants.";
                    } elseif (isset($_POST['confirmation_finale'])) {
                        $conn->beginTransaction();

                        try {
                            $cliNum = resoudreClientReservation($conn, $estConnecte, $cliConnecte, $resNom, $resPrenom, $resTel, $resMail);
                            $points = $estConnecte ? calculerPoints($etape['distance']) : 0;
                            $resNum = $cliNum !== null
                                ? enregistrerReservation($conn, $cliNum, $tarif['tranche'], $resDate, $points, $reduc['prix'], [
                                    array_merge($etape, ['lig_num' => $ligNum]),
                                  ])
                                : null;
                            $okPoints = ($resNum !== null) ? ajusterPointsClient($conn, $cliNum, $points, $reduc['points']) : false;

                            if ($cliNum !== null && $resNum !== null && $okPoints) {
                                $conn->commit();
                                $reservation = [
                                    'reference' => 'VT-' . date('Y') . '-' . str_pad((string) $resNum, 5, '0', STR_PAD_LEFT),
                                    'trajets'   => [[
                                        'lig_num'       => $ligNum,
                                        'depart'        => $etape['depart_nom'],
                                        'arrivee'       => $etape['arrivee_nom'],
                                        'heure'         => $etape['heure'],
                                        'heure_arrivee' => $etape['heure_arrivee'],
                                    ]],
                                    'prix'           => $reduc['prix'],
                                    'reduction'      => $reduc['valeur'],
                                    'points_utilises'=> $reduc['points'],
                                    'points'         => $points,
                                    'date'           => date('d/m/Y', strtotime($resDate)),
                                ];
                            } else {
                                $conn->rollBack();
                                $erreur = "La réservation n'a pas pu être enregistrée. Veuillez réessayer.";
                            }
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $erreur = "Une erreur technique est survenue : " . $e->getMessage();
                        }
                    } else {
                        $afficher_recap = true;
                    }
                }
            }
        }

    /* ----------------------------------------------------------------
     * MODE "multi" : voyage sur plusieurs lignes choisies manuellement (priorite 5)
     * ---------------------------------------------------------------- */
    } elseif ($modeReservation === 'multi') {

        $resDate  = isset($_POST['res_date']) ? trim((string) $_POST['res_date']) : '';
        $legsPost = (isset($_POST['legs']) && is_array($_POST['legs'])) ? $_POST['legs'] : [];

        $resNom    = isset($_POST['res_nom']) ? trim((string) $_POST['res_nom']) : '';
        $resPrenom = isset($_POST['res_prenom']) ? trim((string) $_POST['res_prenom']) : '';
        $resTel    = isset($_POST['res_tel']) ? trim((string) $_POST['res_tel']) : '';
        $resMail   = isset($_POST['res_mail']) ? trim((string) $_POST['res_mail']) : '';

        $coordOk = $estConnecte || ($resNom !== '' && $resPrenom !== '' && $resTel !== '' && $resMail !== '');
        if ($resDate === '' || count($legsPost) < 2) {
            $erreur = 'Veuillez renseigner une date et au moins deux étapes.';
        } elseif (!$coordOk) {
            $erreur = 'Veuillez renseigner vos coordonnées.';
        } else {
            $etapes = [];
            foreach ($legsPost as $leg) {
                $lig   = isset($leg['lig_num']) ? trim((string) $leg['lig_num']) : '';
                $dep   = isset($leg['depart']) ? trim((string) $leg['depart']) : '';
                $arr   = isset($leg['arrivee']) ? trim((string) $leg['arrivee']) : '';
                $heure = isset($leg['heure']) ? trim((string) $leg['heure']) : '';

                if ($lig === '' || $dep === '' || $arr === '' || $heure === '') {
                    $erreur = "Veuillez compléter chaque étape (ligne, arrêt de départ, horaire et arrêt d'arrivée).";
                    break;
                }
                if (!isset($stopsParLigne[$lig])) {
                    $erreur = 'Ligne inconnue dans une des étapes.';
                    break;
                }

                $etape = calculerEtape($stopsParLigne[$lig], $dep, $arr);
                if ($etape === null) {
                    $erreur = "L'arrêt d'arrivée doit être desservi après l'arrêt de départ sur chaque étape.";
                    break;
                }

                $idxDep = trouverIndexArret($stopsParLigne[$lig], $dep);
                if (!in_array($heure, $stopsParLigne[$lig][$idxDep]['horaires'], true)) {
                    $erreur = "Un horaire de départ choisi n'est pas valable pour l'arrêt correspondant.";
                    break;
                }

                $etape['heure']         = $heure;
                $etape['heure_arrivee'] = ajouterMinutes($heure, $etape['duree']);
                $etapes[] = array_merge($etape, ['lig_num' => $lig]);
            }

            if ($erreur === null) {
                date_default_timezone_set('Europe/Paris');
                if (strtotime($resDate . ' ' . $etapes[0]['heure']) < time()) {
                    $erreur = "La date et l'heure de réservation ne peuvent pas être dans le passé.";
                } else {
                    $distanceTotale = 0.0;
                    foreach ($etapes as $etape) {
                        $distanceTotale += $etape['distance'];
                    }
                    $tarif = obtenirTarif($tarifs, $distanceTotale);
                    $pointsUtilises = $estConnecte ? (int) ($_POST['points_utilises'] ?? 0) : 0;
                    $reduc = ($tarif !== null) ? appliquerReduction($reductions, $tarif['prix'], $pointsUtilises, $soldePoints) : null;

                    if ($tarif === null) {
                        $erreur = 'Tarif introuvable pour cette distance.';
                    } elseif ($reduc === null) {
                        $erreur = "Réduction invalide ou points de fidélité insuffisants.";
                    } elseif (isset($_POST['confirmation_finale'])) {
                        $conn->beginTransaction();

                        try {
                            $cliNum = resoudreClientReservation($conn, $estConnecte, $cliConnecte, $resNom, $resPrenom, $resTel, $resMail);
                            $points = $estConnecte ? calculerPoints($distanceTotale) : 0;
                            $resNum = $cliNum !== null
                                ? enregistrerReservation($conn, $cliNum, $tarif['tranche'], $resDate, $points, $reduc['prix'], $etapes)
                                : null;
                            $okPoints = ($resNum !== null) ? ajusterPointsClient($conn, $cliNum, $points, $reduc['points']) : false;

                            if ($cliNum !== null && $resNum !== null && $okPoints) {
                                $conn->commit();
                                $trajets = [];
                                foreach ($etapes as $etape) {
                                    $trajets[] = [
                                        'lig_num'       => $etape['lig_num'],
                                        'depart'        => $etape['depart_nom'],
                                        'arrivee'       => $etape['arrivee_nom'],
                                        'heure'         => $etape['heure'],
                                        'heure_arrivee' => $etape['heure_arrivee'],
                                    ];
                                }
                                $reservation = [
                                    'reference' => 'VT-' . date('Y') . '-' . str_pad((string) $resNum, 5, '0', STR_PAD_LEFT),
                                    'trajets'   => $trajets,
                                    'prix'           => $reduc['prix'],
                                    'reduction'      => $reduc['valeur'],
                                    'points_utilises'=> $reduc['points'],
                                    'points'         => $points,
                                    'date'           => date('d/m/Y', strtotime($resDate)),
                                ];
                            } else {
                                $conn->rollBack();
                                $erreur = "La réservation n'a pas pu être enregistrée. Veuillez réessayer.";
                            }
                        } catch (Exception $e) {
                            $conn->rollBack();
                            $erreur = "Une erreur technique est survenue : " . $e->getMessage();
                        }
                    } else {
                        $afficher_recap = true;
                    }
                }
            }
        }
    }
}

/* ===================== PREPARATION DU RECAPITULATIF (etape intermediaire) ===================== */
$recapTrajets        = [];
$recapPrixBase       = 0;   // prix avant reduction
$recapReduction      = 0;   // euros de remise (points depenses)
$recapPointsUtilises = 0;   // points depenses
$recapPrix           = 0;   // prix final paye
$recapPoints         = 0;   // points gagnes
$recapPassager       = '';
$recapDate           = $resDate ?? '';
$nomCompte           = $estConnecte ? trim(($cliConnecte['prenom'] ?? '') . ' ' . ($cliConnecte['nom'] ?? '')) : '';

if ($afficher_recap === true) {
    if ($modeReservation === 'ligne') {
        $recapTrajets = [[
            'lig_num'       => $ligNum,
            'depart'        => $depart,
            'arrivee'       => $arrivee,
            'heure'         => $resHeure,
            'heure_arrivee' => null,
        ]];
        $recapPrixBase = $prix;
        $recapPoints   = $estConnecte ? calculerPoints($distance) : 0;
        $recapPassager = $estConnecte ? $nomCompte : $resPrenom . ' ' . $resNom;
    } elseif ($modeReservation === 'partielle') {
        $recapTrajets = [[
            'lig_num'       => $ligNum,
            'depart'        => $etape['depart_nom'],
            'arrivee'       => $etape['arrivee_nom'],
            'heure'         => $etape['heure'],
            'heure_arrivee' => $etape['heure_arrivee'],
        ]];
        $recapPrixBase = $tarif['prix'];
        $recapPoints   = $estConnecte ? calculerPoints($etape['distance']) : 0;
        $recapPassager = $estConnecte ? $nomCompte : $resPrenom . ' ' . $resNom;
    } else { // multi
        $distanceTotaleRecap = 0.0;
        foreach ($etapes as $etape) {
            $distanceTotaleRecap += $etape['distance'];
            $recapTrajets[] = [
                'lig_num'       => $etape['lig_num'],
                'depart'        => $etape['depart_nom'],
                'arrivee'       => $etape['arrivee_nom'],
                'heure'         => $etape['heure'],
                'heure_arrivee' => $etape['heure_arrivee'],
            ];
        }
        $recapPrixBase = $tarif['prix'];
        $recapPoints   = $estConnecte ? calculerPoints($distanceTotaleRecap) : 0;
        $recapPassager = $estConnecte ? $nomCompte : $resPrenom . ' ' . $resNom;
    }

    // Reduction fidelite calculee dans le handler ($reduc) : on l'applique au recapitulatif
    $recapReduction      = $reduc['valeur'] ?? 0;
    $recapPointsUtilises = $reduc['points'] ?? 0;
    $recapPrix           = $reduc['prix'] ?? $recapPrixBase;
}

/* ===================== AFFICHAGE ===================== */
date_default_timezone_set('Europe/Paris');
$aujourdhui = date('Y-m-d');
$heureActuelle = date('H:i');

/* Bloc "utiliser des points" (fonction 12), construit une fois et reutilise dans les 3 onglets.
   Affiche uniquement pour un client connecte : propose les paliers de reduction qu'il peut s'offrir. */
$blocReductionHtml = '';
if ($estConnecte) {
    $reductionsDispo = array_filter(
        $reductions,
        fn($r) => (int) ($r['RED_NB_POINTS'] ?? $r['red_nb_points']) <= $soldePoints
    );
    $selPoints = (int) ($_POST['points_utilises'] ?? 0);

    ob_start(); ?>
    <div class="mb-3">
        <label class="form-label">
            <i class="bi bi-gift"></i> Utiliser mes points
            <span class="text-muted">(solde : <?= (int) $soldePoints ?> pts)</span>
        </label>
        <?php if (empty($reductionsDispo)): ?>
            <p class="text-muted small mb-0">Pas assez de points pour bénéficier d'une réduction pour le moment.</p>
            <input type="hidden" name="points_utilises" value="0">
        <?php else: ?>
            <select class="form-select" name="points_utilises">
                <option value="0">Aucune réduction</option>
                <?php foreach ($reductionsDispo as $r):
                    $rp = (int) ($r['RED_NB_POINTS'] ?? $r['red_nb_points']);
                    $rv = (int) ($r['RED_VALEUR'] ?? $r['red_valeur']);
                ?>
                    <option value="<?= $rp ?>" <?= $selPoints === $rp ? 'selected' : '' ?>>
                        Utiliser <?= $rp ?> points (&minus;<?= $rv ?> &euro;)
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
    <?php
    $blocReductionHtml = ob_get_clean();
}

include __DIR__ . '/../includes/header.php';
?>

<main class="container my-4 flex-grow-1">
    <h1 class="mb-4 text-center">Acheter un billet</h1>

    <?php if ($reservation !== null): ?>

        <section class="p-4 p-md-5 text-center mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <i class="bi bi-check-square fs-1 text-success"></i>
            <h2 class="h4 mt-2">Réservation confirmée</h2>
            <p class="text-muted">Votre réservation a bien été enregistrée.</p>

            <div class="card text-start mx-auto mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Référence</span>
                        <span class="fw-semibold"><?= htmlspecialchars($reservation['reference']) ?></span>
                    </div>
                    <?php foreach ($reservation['trajets'] as $i => $t): ?>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">
                                <?= count($reservation['trajets']) > 1 ? 'Étape ' . ($i + 1) . ' ' : '' ?>(Ligne <?= htmlspecialchars($t['lig_num']) ?>)
                            </span>
                            <span class="text-end">
                                <?= htmlspecialchars($t['depart']) ?> &rarr; <?= htmlspecialchars($t['arrivee']) ?>
                                <br><small class="text-muted">Départ à <?= htmlspecialchars($t['heure']) ?><?php if (!empty($t['heure_arrivee'])): ?> &middot; Arrivée à <?= htmlspecialchars($t['heure_arrivee']) ?><?php endif; ?></small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Date</span>
                        <span class="fw-semibold">Le <?= htmlspecialchars($reservation['date']) ?></span>
                    </div>
                    <?php if (!empty($reservation['reduction'])): ?>
                        <div class="d-flex justify-content-between mb-1 text-success">
                            <span>Réduction (<?= htmlspecialchars((string) $reservation['points_utilises']) ?> points utilisés)</span>
                            <span>&minus;<?= htmlspecialchars((string) $reservation['reduction']) ?> &euro;</span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between<?= !empty($reservation['points']) ? ' mb-3' : '' ?>">
                        <span class="text-muted">Montant payé</span>
                        <span class="fw-semibold"><?= htmlspecialchars((string) $reservation['prix']) ?> &euro;</span>
                    </div>
                    <?php if (!empty($reservation['points'])): ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Points fidélité gagnés</span>
                            <span class="fw-semibold text-success">+<?= htmlspecialchars((string) $reservation['points']) ?> pts</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a href="reserver.php" class="btn btn-dark mt-4 px-4">Nouvelle réservation</a>
        </section>

    <?php elseif ($afficher_recap === true): ?>

        <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:600px;">
            <h2 class="h4 mb-4 text-center text-secondary"><i class="bi bi-wallet2"></i> Vérifiez votre trajet</h2>

            <div class="card text-start mx-auto mb-4 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Passager</span>
                        <span class="fw-semibold"><?= htmlspecialchars($recapPassager) ?></span>
                    </div>
                    <?php foreach ($recapTrajets as $i => $t): ?>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">
                                <?= count($recapTrajets) > 1 ? 'Étape ' . ($i + 1) . ' ' : '' ?>(Ligne <?= htmlspecialchars($t['lig_num']) ?>)
                            </span>
                            <span class="text-end">
                                <?= htmlspecialchars($t['depart']) ?> <br>&rarr; <?= htmlspecialchars($t['arrivee']) ?>
                                <br><small class="text-muted">Départ à <?= htmlspecialchars($t['heure']) ?><?php if (!empty($t['heure_arrivee'])): ?><br>Arrivée à <?= htmlspecialchars($t['heure_arrivee']) ?><?php endif; ?></small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Date du voyage</span>
                        <span>Le <?= htmlspecialchars(date('d/m/Y', strtotime($recapDate))) ?></span>
                    </div>
                    <hr>
                    <?php if ($recapReduction > 0): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Sous-total</span>
                            <span><?= htmlspecialchars((string) $recapPrixBase) ?> &euro;</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Réduction (<?= htmlspecialchars((string) $recapPointsUtilises) ?> points)</span>
                            <span>&minus;<?= htmlspecialchars((string) $recapReduction) ?> &euro;</span>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center fs-5">
                        <span class="text-muted">Prix total</span>
                        <span class="fw-bold fs-3"><?= htmlspecialchars((string) $recapPrix) ?> &euro;</span>
                    </div>
                    <?php if ($recapPoints > 0): ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted">Points fidélité gagnés</span>
                            <span class="fw-semibold text-success">+<?= htmlspecialchars((string) $recapPoints) ?> pts</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="reserver.php" class="d-flex justify-content-between mt-4">
                <input type="hidden" name="mode_reservation" value="<?= htmlspecialchars($modeReservation) ?>">
                <input type="hidden" name="res_date" value="<?= htmlspecialchars($resDate) ?>">
                <?php if ($estConnecte): ?>
                    <input type="hidden" name="points_utilises" value="<?= (int) $recapPointsUtilises ?>">
                <?php endif; ?>

                <?php if ($modeReservation === 'ligne'): ?>
                    <input type="hidden" name="lig_num" value="<?= htmlspecialchars($ligNum) ?>">
                    <input type="hidden" name="res_heure" value="<?= htmlspecialchars($resHeure) ?>">
                    <input type="hidden" name="res_nom" value="<?= htmlspecialchars($resNom) ?>">
                    <input type="hidden" name="res_prenom" value="<?= htmlspecialchars($resPrenom) ?>">
                    <input type="hidden" name="res_tel" value="<?= htmlspecialchars($resTel) ?>">
                    <input type="hidden" name="res_mail" value="<?= htmlspecialchars($resMail) ?>">
                <?php elseif ($modeReservation === 'partielle'): ?>
                    <input type="hidden" name="lig_num" value="<?= htmlspecialchars($ligNum) ?>">
                    <input type="hidden" name="arret_depart" value="<?= htmlspecialchars($arretDepart) ?>">
                    <input type="hidden" name="res_heure" value="<?= htmlspecialchars($resHeure) ?>">
                    <input type="hidden" name="arret_arrivee" value="<?= htmlspecialchars($arretArrivee) ?>">
                    <input type="hidden" name="res_nom" value="<?= htmlspecialchars($resNom) ?>">
                    <input type="hidden" name="res_prenom" value="<?= htmlspecialchars($resPrenom) ?>">
                    <input type="hidden" name="res_tel" value="<?= htmlspecialchars($resTel) ?>">
                    <input type="hidden" name="res_mail" value="<?= htmlspecialchars($resMail) ?>">
                <?php else: ?>
                    <?php foreach ($etapes as $i => $etape): ?>
                        <input type="hidden" name="legs[<?= $i ?>][lig_num]" value="<?= htmlspecialchars($etape['lig_num']) ?>">
                        <input type="hidden" name="legs[<?= $i ?>][depart]" value="<?= htmlspecialchars($etape['depart_code']) ?>">
                        <input type="hidden" name="legs[<?= $i ?>][heure]" value="<?= htmlspecialchars($etape['heure']) ?>">
                        <input type="hidden" name="legs[<?= $i ?>][arrivee]" value="<?= htmlspecialchars($etape['arrivee_code']) ?>">
                    <?php endforeach; ?>
                    <?php if (!$estConnecte): ?>
                        <input type="hidden" name="res_nom" value="<?= htmlspecialchars($resNom) ?>">
                        <input type="hidden" name="res_prenom" value="<?= htmlspecialchars($resPrenom) ?>">
                        <input type="hidden" name="res_tel" value="<?= htmlspecialchars($resTel) ?>">
                        <input type="hidden" name="res_mail" value="<?= htmlspecialchars($resMail) ?>">
                    <?php endif; ?>
                <?php endif; ?>

                <a href="reserver.php" class="btn btn-outline-secondary px-4 py-2">Annuler</a>
                <button type="submit" name="confirmation_finale" value="1" class="btn btn-dark px-4 py-2">Confirmer et Payer</button>
            </form>
        </section>

    <?php else: ?>

        <section class="p-4 p-md-5 mx-auto" style="background:#f3f3f5; border-radius:1rem; max-width:1000px;">

            <?php if ($erreur !== null): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <ul class="nav nav-pills justify-content-center mb-4 gap-2 flex-wrap" id="modeTabs" role="tablist"
                style="--bs-nav-pills-link-active-bg: #292121; --bs-nav-pills-link-active-color: #ffffff; 
                --bs-nav-link-color: #574949; --bs-nav-link-hover-color: #292121;">
                
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $modeReservation === 'ligne' ? 'active' : '' ?>" id="tab-ligne-tab"
                            data-bs-toggle="pill" data-bs-target="#tab-ligne" type="button" role="tab">
                        <i class="bi bi-bus-front"></i> Ligne complète
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $modeReservation === 'partielle' ? 'active' : '' ?>" id="tab-partielle-tab"
                            data-bs-toggle="pill" data-bs-target="#tab-partielle" type="button" role="tab">
                        <i class="bi bi-signpost-split"></i> Partie d'une ligne
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $modeReservation === 'multi' ? 'active' : '' ?>" id="tab-multi-tab"
                            data-bs-toggle="pill" data-bs-target="#tab-multi" type="button" role="tab">
                        <i class="bi bi-signpost-2"></i> Plusieurs lignes
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ===================== ONGLET 1 : LIGNE COMPLETE ===================== -->
                <div class="tab-pane fade <?= $modeReservation === 'ligne' ? 'show active' : '' ?>" id="tab-ligne" role="tabpanel">
                    <form method="post" action="reserver.php">
                        <input type="hidden" name="mode_reservation" value="ligne">
                        <div class="row g-4">

                            <div class="col-md-6 border-end">
                                <h3 class="h5 mb-4 text-secondary"><i class="bi bi-bus-front"></i> Votre trajet</h3>

                                <div class="mb-3">
                                    <label for="lig_num" class="form-label">Choix de la ligne</label>
                                    <select class="form-select" id="lig_num" name="lig_num" required>
                                        <option value="" selected disabled>Sélectionnez une ligne</option>
                                        <?php foreach ($lignes as $l):
                                            $l_num = $l['LIG_NUM'] ?? $l['lig_num'];
                                            $l_dep = $l['DEPART'] ?? $l['depart'];
                                            $l_arr = $l['ARRIVEE'] ?? $l['arrivee'];
                                        ?>
                                            <option value="<?= htmlspecialchars($l_num) ?>"
                                                <?= ($modeReservation === 'ligne' && ($_POST['lig_num'] ?? '') === $l_num) ? 'selected' : '' ?>>
                                                Ligne <?= htmlspecialchars($l_num) ?> :
                                                <?= htmlspecialchars($l_dep) ?> &rarr; <?= htmlspecialchars($l_arr) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-sm-6 mb-3">
                                        <label for="res_date" class="form-label">Date du voyage</label>
                                        <input type="date" class="form-control" id="res_date" name="res_date" min="<?= $aujourdhui ?>" required
                                               value="<?= $modeReservation === 'ligne' ? htmlspecialchars($_POST['res_date'] ?? '') : '' ?>">
                                    </div>
                                    <div class="col-sm-6 mb-3">
                                        <label for="res_heure" class="form-label">Horaire de départ</label>
                                        <select class="form-select" id="res_heure" name="res_heure" required disabled>
                                            <option value="" selected disabled>Choisissez d'abord une ligne</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h3 class="h5 mb-4 text-secondary"><i class="bi bi-person"></i> Vos coordonnées</h3>

                                <?php if ($estConnecte): ?>
                                    <div class="alert alert-light border d-flex align-items-center gap-2">
                                        <i class="bi bi-person-check text-success fs-4"></i>
                                        <span>Réservation au nom de
                                            <strong><?= htmlspecialchars(trim(($cliConnecte['prenom'] ?? '') . ' ' . ($cliConnecte['nom'] ?? ''))) ?></strong>.
                                            Vos coordonnées de compte seront utilisées.</span>
                                    </div>
                                    <?= $blocReductionHtml ?>
                                <?php else: ?>
                                    <div class="row">
                                        <div class="col-sm-6 mb-3">
                                            <label for="res_nom" class="form-label">Nom</label>
                                            <input type="text" class="form-control" id="res_nom" name="res_nom" placeholder="Pork" required
                                                   value="<?= $modeReservation === 'ligne' ? htmlspecialchars($_POST['res_nom'] ?? '') : '' ?>">
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <label for="res_prenom" class="form-label">Prénom</label>
                                            <input type="text" class="form-control" id="res_prenom" name="res_prenom" placeholder="John" required
                                                   value="<?= $modeReservation === 'ligne' ? htmlspecialchars($_POST['res_prenom'] ?? '') : '' ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="res_tel" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="res_tel" name="res_tel" placeholder="0767676767 ou 07 67 67 67 67" pattern="[0-9]{2}( ?[0-9]{2}){4}" required
                                               value="<?= $modeReservation === 'ligne' ? htmlspecialchars($_POST['res_tel'] ?? '') : '' ?>">
                                    </div>

                                    <div class="mb-4">
                                        <label for="res_mail" class="form-label">Adresse mail</label>
                                        <input type="email" class="form-control" id="res_mail" name="res_mail" placeholder="johnpork@gmail.com" required
                                               value="<?= $modeReservation === 'ligne' ? htmlspecialchars($_POST['res_mail'] ?? '') : '' ?>">
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-dark py-2 px-5 fs-5">Calculer le prix</button>
                        </div>
                    </form>
                </div>

                <!-- ===================== ONGLET 2 : PARTIE D'UNE LIGNE ===================== -->
                <div class="tab-pane fade <?= $modeReservation === 'partielle' ? 'show active' : '' ?>" id="tab-partielle" role="tabpanel">
                    <form method="post" action="reserver.php" id="form-partielle">
                        <input type="hidden" name="mode_reservation" value="partielle">
                        <div class="row g-4">

                            <div class="col-md-6 border-end">
                                <h3 class="h5 mb-4 text-secondary"><i class="bi bi-signpost-split"></i> Votre trajet</h3>
                                <p class="text-muted small">Montez et descendez aux arrêts de votre choix sur une même ligne.</p>

                                <div class="mb-3">
                                    <label for="p_lig_num" class="form-label">Choix de la ligne</label>
                                    <select class="form-select" id="p_lig_num" name="lig_num" required>
                                        <option value="" selected disabled>Sélectionnez une ligne</option>
                                        <?php foreach ($lignes as $l):
                                            $l_num = $l['LIG_NUM'] ?? $l['lig_num'];
                                            $l_dep = $l['DEPART'] ?? $l['depart'];
                                            $l_arr = $l['ARRIVEE'] ?? $l['arrivee'];
                                        ?>
                                            <option value="<?= htmlspecialchars($l_num) ?>"
                                                <?= ($modeReservation === 'partielle' && (($_POST['lig_num'] ?? '') === $l_num || ($_GET['lig_num'] ?? '') === $l_num)) ? 'selected' : '' ?>>
                                                Ligne <?= htmlspecialchars($l_num) ?> :
                                                <?= htmlspecialchars($l_dep) ?> &rarr; <?= htmlspecialchars($l_arr) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-sm-7 mb-3">
                                        <label for="p_arret_depart" class="form-label">Arrêt de départ</label>
                                        <select class="form-select" id="p_arret_depart" name="arret_depart" required disabled>
                                            <option value="" selected disabled>Choisissez d'abord une ligne</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-5 mb-3">
                                        <label for="p_res_heure" class="form-label">Horaire de départ</label>
                                        <select class="form-select" id="p_res_heure" name="res_heure" required disabled>
                                            <option value="" selected disabled>Arrêt d'abord</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="p_arret_arrivee" class="form-label">Arrêt d'arrivée</label>
                                    <select class="form-select" id="p_arret_arrivee" name="arret_arrivee" required disabled>
                                        <option value="" selected disabled>Choisissez d'abord un arrêt de départ</option>
                                    </select>
                                    <div class="form-text">L'heure d'arrivée vous sera indiquée au calcul du prix.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="p_res_date" class="form-label">Date du voyage</label>
                                    <input type="date" class="form-control" id="p_res_date" name="res_date" min="<?= $aujourdhui ?>" required
                                           value="<?= $modeReservation === 'partielle' ? htmlspecialchars($_POST['res_date'] ?? '') : '' ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h3 class="h5 mb-4 text-secondary"><i class="bi bi-person"></i> Vos coordonnées</h3>

                                <?php if ($estConnecte): ?>
                                    <div class="alert alert-light border d-flex align-items-center gap-2">
                                        <i class="bi bi-person-check text-success fs-4"></i>
                                        <span>Réservation au nom de
                                            <strong><?= htmlspecialchars(trim(($cliConnecte['prenom'] ?? '') . ' ' . ($cliConnecte['nom'] ?? ''))) ?></strong>.
                                            Vos coordonnées de compte seront utilisées.</span>
                                    </div>
                                    <?= $blocReductionHtml ?>
                                <?php else: ?>
                                    <div class="row">
                                        <div class="col-sm-6 mb-3">
                                            <label for="p_res_nom" class="form-label">Nom</label>
                                            <input type="text" class="form-control" id="p_res_nom" name="res_nom" placeholder="Pork" required
                                                   value="<?= $modeReservation === 'partielle' ? htmlspecialchars($_POST['res_nom'] ?? '') : '' ?>">
                                        </div>
                                        <div class="col-sm-6 mb-3">
                                            <label for="p_res_prenom" class="form-label">Prénom</label>
                                            <input type="text" class="form-control" id="p_res_prenom" name="res_prenom" placeholder="John" required
                                                   value="<?= $modeReservation === 'partielle' ? htmlspecialchars($_POST['res_prenom'] ?? '') : '' ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="p_res_tel" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="p_res_tel" name="res_tel" placeholder="0767676767 ou 07 67 67 67 67" pattern="[0-9]{2}( ?[0-9]{2}){4}" required
                                               value="<?= $modeReservation === 'partielle' ? htmlspecialchars($_POST['res_tel'] ?? '') : '' ?>">
                                    </div>

                                    <div class="mb-4">
                                        <label for="p_res_mail" class="form-label">Adresse mail</label>
                                        <input type="email" class="form-control" id="p_res_mail" name="res_mail" placeholder="johnpork@gmail.com" required
                                               value="<?= $modeReservation === 'partielle' ? htmlspecialchars($_POST['res_mail'] ?? '') : '' ?>">
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-dark py-2 px-5 fs-5">Calculer le prix</button>
                        </div>
                    </form>
                </div>

                <!-- ===================== ONGLET 3 : PLUSIEURS LIGNES ===================== -->
                <div class="tab-pane fade <?= $modeReservation === 'multi' ? 'show active' : '' ?>" id="tab-multi" role="tabpanel">
                        <form method="post" action="reserver.php" id="form-multi">
                            <input type="hidden" name="mode_reservation" value="multi">

                            <p class="text-muted small">
                                Composez votre voyage en ajoutant les étapes (lignes et arrêts) dans l'ordre du parcours.
                                Le prix est calculé sur la distance totale du voyage.
                            </p>

                            <div class="mb-3" style="max-width: 320px;">
                                <label for="m_res_date" class="form-label">Date du voyage</label>
                                <input type="date" class="form-control" id="m_res_date" name="res_date" min="<?= $aujourdhui ?>" required
                                       value="<?= $modeReservation === 'multi' ? htmlspecialchars($_POST['res_date'] ?? '') : '' ?>">
                            </div>

                            <div id="legs-container"></div>

                            <button type="button" id="add-leg-btn" class="btn btn-outline-secondary btn-sm mb-4">
                                <i class="bi bi-plus-lg"></i> Ajouter une étape
                            </button>

                            <hr>
                            <h3 class="h5 mb-3 text-secondary"><i class="bi bi-person"></i> Vos coordonnées</h3>
                            <?php if ($estConnecte): ?>
                                <div class="alert alert-light border d-flex align-items-center gap-2 mb-4">
                                    <i class="bi bi-person-check text-success fs-4"></i>
                                    <span>Réservation au nom de
                                        <strong><?= htmlspecialchars(trim(($cliConnecte['prenom'] ?? '') . ' ' . ($cliConnecte['nom'] ?? ''))) ?></strong>.
                                        Vos coordonnées de compte seront utilisées.</span>
                                </div>
                                <?= $blocReductionHtml ?>
                            <?php else: ?>
                                <div class="row" style="max-width: 720px;">
                                    <div class="col-sm-3 mb-3">
                                        <label for="m_res_nom" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="m_res_nom" name="res_nom" placeholder="Pork" required
                                               value="<?= $modeReservation === 'multi' ? htmlspecialchars($_POST['res_nom'] ?? '') : '' ?>">
                                    </div>
                                    <div class="col-sm-3 mb-3">
                                        <label for="m_res_prenom" class="form-label">Prénom</label>
                                        <input type="text" class="form-control" id="m_res_prenom" name="res_prenom" placeholder="John" required
                                               value="<?= $modeReservation === 'multi' ? htmlspecialchars($_POST['res_prenom'] ?? '') : '' ?>">
                                    </div>
                                    <div class="col-sm-3 mb-3">
                                        <label for="m_res_tel" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="m_res_tel" name="res_tel" placeholder="0767676767 ou 07 67 67 67 67" pattern="[0-9]{2}( ?[0-9]{2}){4}" required
                                               value="<?= $modeReservation === 'multi' ? htmlspecialchars($_POST['res_tel'] ?? '') : '' ?>">
                                    </div>
                                    <div class="col-sm-3 mb-3">
                                        <label for="m_res_mail" class="form-label">Adresse mail</label>
                                        <input type="email" class="form-control" id="m_res_mail" name="res_mail" placeholder="johnpork@gmail.com" required
                                               value="<?= $modeReservation === 'multi' ? htmlspecialchars($_POST['res_mail'] ?? '') : '' ?>">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="text-center">
                                <button type="submit" class="btn btn-dark py-2 px-5 fs-5">Calculer le prix</button>
                            </div>
                        </form>

                        <!-- Modèle d'une étape, dupliqué en JS -->
                        <template id="leg-template">
                            <div class="leg-row card border-0 shadow-sm mb-3">
                                <div class="card-body row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Ligne</label>
                                        <select class="form-select leg-ligne" name="legs[__INDEX__][lig_num]" required>
                                            <option value="" selected disabled>Sélectionnez une ligne</option>
                                            <?php foreach ($lignes as $l):
                                                $l_num = $l['LIG_NUM'] ?? $l['lig_num'];
                                                $l_dep = $l['DEPART'] ?? $l['depart'];
                                                $l_arr = $l['ARRIVEE'] ?? $l['arrivee'];
                                            ?>
                                                <option value="<?= htmlspecialchars($l_num) ?>">
                                                    Ligne <?= htmlspecialchars($l_num) ?> : <?= htmlspecialchars($l_dep) ?> &rarr; <?= htmlspecialchars($l_arr) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Arrêt de départ</label>
                                        <select class="form-select leg-depart" name="legs[__INDEX__][depart]" required disabled>
                                            <option value="" selected disabled>Choisissez d'abord une ligne</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Horaire</label>
                                        <select class="form-select leg-heure" name="legs[__INDEX__][heure]" required disabled>
                                            <option value="" selected disabled>—</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Arrêt d'arrivée</label>
                                        <select class="form-select leg-arrivee" name="legs[__INDEX__][arrivee]" required disabled>
                                            <option value="" selected disabled>Arrêt de départ d'abord</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm leg-remove">
                                            <i class="bi bi-trash"></i> Retirer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                </div>

            </div>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const today = '<?= $aujourdhui ?>';
                const nowTime = '<?= $heureActuelle ?>';
                const horairesParLigne = <?= json_encode($horairesParLigne) ?>;
                const stopsParLigne = <?= json_encode($stopsParLigne) ?>;

                /* ===================== ONGLET 1 : LIGNE COMPLETE ===================== */
                const dateInput = document.getElementById('res_date');
                const timeSelect = document.getElementById('res_heure');
                const lineSelect = document.getElementById('lig_num');
                const heurePrefill = <?= json_encode($modeReservation === 'ligne' ? ($_POST['res_heure'] ?? '') : '') ?>;

                function updateHours() {
                    const selectedLine = lineSelect.value;
                    const selectedDate = dateInput.value;

                    timeSelect.innerHTML = '<option value="" selected disabled>Choisissez un horaire</option>';

                    if (selectedLine && horairesParLigne[selectedLine]) {
                        const horaires = horairesParLigne[selectedLine];
                        let count = 0;

                        horaires.forEach(heure => {
                            if (selectedDate === today && heure < nowTime) {
                                return;
                            }

                            const option = document.createElement('option');
                            option.value = heure;
                            option.textContent = heure;
                            timeSelect.appendChild(option);
                            count++;
                        });

                        if (count > 0) {
                            timeSelect.disabled = false;
                            if (heurePrefill && horaires.includes(heurePrefill)) {
                                timeSelect.value = heurePrefill;
                            }
                        } else {
                            timeSelect.innerHTML = '<option value="" selected disabled>Aucun horaire disponible pour ce jour</option>';
                            timeSelect.disabled = true;
                        }
                    } else {
                        timeSelect.innerHTML = '<option value="" selected disabled>Choisissez d\'abord une ligne</option>';
                        timeSelect.disabled = true;
                    }
                }

                lineSelect.addEventListener('change', updateHours);
                dateInput.addEventListener('change', updateHours);

                if (lineSelect.value) {
                    updateHours();
                }

                /* ===================== FONCTIONS PARTAGEES : ARRETS D'UNE LIGNE ===================== */
                // Arrets pouvant servir de depart (tous sauf le terminus, qui a heure === null)
                function peuplerArretsDepart(ligNum, departSelect) {
                    departSelect.innerHTML = '<option value="" selected disabled>Choisissez un arrêt de départ</option>';
                    const stops = stopsParLigne[ligNum] || [];

                    stops.forEach(s => {
                        if (s.heure !== null) {
                            const option = document.createElement('option');
                            option.value = s.code;
                            option.textContent = s.nom;
                            departSelect.appendChild(option);
                        }
                    });

                    departSelect.disabled = stops.length === 0;
                }

                // Horaires de depart disponibles a un arret donne (filtre les heures passees si date = aujourd'hui)
                function peuplerHoraires(ligNum, stopCode, heureSelect, dateValue) {
                    // On memorise l'horaire deja choisi pour le restaurer apres reconstruction
                    // (ex. quand l'utilisateur renseigne la date apres avoir choisi l'horaire).
                    const horairePrecedent = heureSelect.value;

                    heureSelect.innerHTML = '<option value="" selected disabled>Choisissez un horaire</option>';
                    const stops = stopsParLigne[ligNum] || [];
                    const stop = stops.find(s => s.code === stopCode);
                    const horaires = (stop && stop.horaires) ? stop.horaires : [];
                    let count = 0;

                    horaires.forEach(h => {
                        if (dateValue === today && h < nowTime) {
                            return;
                        }
                        const o = document.createElement('option');
                        o.value = h;
                        o.textContent = h;
                        heureSelect.appendChild(o);
                        count++;
                    });

                    if (count === 0) {
                        heureSelect.innerHTML = '<option value="" selected disabled>Aucun horaire disponible</option>';
                    }
                    heureSelect.disabled = count === 0;

                    // Restaure le choix precedent s'il est toujours propose
                    if (horairePrecedent && [...heureSelect.options].some(o => o.value === horairePrecedent)) {
                        heureSelect.value = horairePrecedent;
                    }
                }

                // Arrets d'arrivee possibles (situes apres l'arret de depart). L'heure d'arrivee n'est PAS
                // affichee ici : elle est calculee et montree au recapitulatif.
                function peuplerArretsArrivee(ligNum, departCode, arriveeSelect) {
                    arriveeSelect.innerHTML = '<option value="" selected disabled>Choisissez un arrêt d\'arrivée</option>';
                    const stops = stopsParLigne[ligNum] || [];
                    const depIndex = stops.findIndex(s => s.code === departCode);

                    if (depIndex === -1) {
                        arriveeSelect.disabled = true;
                        return;
                    }

                    stops.forEach((s, idx) => {
                        if (idx > depIndex) {
                            const option = document.createElement('option');
                            option.value = s.code;
                            option.textContent = s.nom;
                            arriveeSelect.appendChild(option);
                        }
                    });

                    arriveeSelect.disabled = false;
                }

                /* ===================== ONGLET 2 : PARTIE D'UNE LIGNE ===================== */
                const pLigSelect   = document.getElementById('p_lig_num');
                const pDepSelect   = document.getElementById('p_arret_depart');
                const pHeureSelect = document.getElementById('p_res_heure');
                const pArrSelect   = document.getElementById('p_arret_arrivee');
                const pDateInput   = document.getElementById('p_res_date');
                const pPrefill = <?= json_encode($modeReservation === 'partielle' ? [
                    'depart'  => $_POST['arret_depart'] ?? $_GET['arret_depart'] ?? '',
                    'heure'   => $_POST['res_heure'] ?? $_GET['res_heure'] ?? '',
                    'arrivee' => $_POST['arret_arrivee'] ?? $_GET['arret_arrivee'] ?? '',
                ] : ['depart' => '', 'heure' => '', 'arrivee' => '']) ?>;

                if (pLigSelect) {
                    pLigSelect.addEventListener('change', () => {
                        peuplerArretsDepart(pLigSelect.value, pDepSelect);
                        pHeureSelect.innerHTML = '<option value="" selected disabled>Arrêt d\'abord</option>';
                        pHeureSelect.disabled = true;
                        pArrSelect.innerHTML = '<option value="" selected disabled>Choisissez d\'abord un arrêt de départ</option>';
                        pArrSelect.disabled = true;
                    });

                    pDepSelect.addEventListener('change', () => {
                        peuplerHoraires(pLigSelect.value, pDepSelect.value, pHeureSelect, pDateInput.value);
                        peuplerArretsArrivee(pLigSelect.value, pDepSelect.value, pArrSelect);
                    });

                    pDateInput.addEventListener('change', () => {
                        if (pDepSelect.value) {
                            peuplerHoraires(pLigSelect.value, pDepSelect.value, pHeureSelect, pDateInput.value);
                        }
                    });

                    if (pLigSelect.value) {
                        peuplerArretsDepart(pLigSelect.value, pDepSelect);
                        if (pPrefill.depart) {
                            pDepSelect.value = pPrefill.depart;
                            peuplerHoraires(pLigSelect.value, pDepSelect.value, pHeureSelect, pDateInput.value);
                            if (pPrefill.heure) {
                                pHeureSelect.value = pPrefill.heure;
                            }
                            peuplerArretsArrivee(pLigSelect.value, pDepSelect.value, pArrSelect);
                            if (pPrefill.arrivee) {
                                pArrSelect.value = pPrefill.arrivee;
                            }
                        }
                    }
                }

                /* ===================== ONGLET 3 : PLUSIEURS LIGNES ===================== */
                const legsContainer = document.getElementById('legs-container');
                const legTemplate = document.getElementById('leg-template');
                const mDateInput = document.getElementById('m_res_date');
                const legsPrefill = <?= json_encode($modeReservation === 'multi' ? array_values($legsPost) : []) ?>;
                let legIndex = 0;

                function mDateValue() {
                    return mDateInput ? mDateInput.value : '';
                }

                function initLegRow(row) {
                    const ligSelect = row.querySelector('.leg-ligne');
                    const depSelect = row.querySelector('.leg-depart');
                    const heuSelect = row.querySelector('.leg-heure');
                    const arrSelect = row.querySelector('.leg-arrivee');

                    ligSelect.addEventListener('change', () => {
                        peuplerArretsDepart(ligSelect.value, depSelect);
                        heuSelect.innerHTML = '<option value="" selected disabled>—</option>';
                        heuSelect.disabled = true;
                        arrSelect.innerHTML = '<option value="" selected disabled>Arrêt de départ d\'abord</option>';
                        arrSelect.disabled = true;
                    });

                    depSelect.addEventListener('change', () => {
                        peuplerHoraires(ligSelect.value, depSelect.value, heuSelect, mDateValue());
                        peuplerArretsArrivee(ligSelect.value, depSelect.value, arrSelect);
                    });

                    row.querySelector('.leg-remove').addEventListener('click', () => {
                        row.remove();
                        updateRemoveButtons();
                    });
                }

                function updateRemoveButtons() {
                    const rows = legsContainer.querySelectorAll('.leg-row');
                    rows.forEach(r => {
                        r.querySelector('.leg-remove').disabled = rows.length <= 2;
                    });
                }

                function ajouterEtape(prefill) {
                    const clone = legTemplate.content.cloneNode(true);
                    const row = clone.querySelector('.leg-row');

                    row.querySelectorAll('[name]').forEach(el => {
                        el.name = el.name.replace('__INDEX__', legIndex);
                    });
                    legIndex++;

                    legsContainer.appendChild(row);
                    const addedRow = legsContainer.lastElementChild;
                    initLegRow(addedRow);

                    if (prefill && prefill.lig_num) {
                        const ligSelect = addedRow.querySelector('.leg-ligne');
                        const depSelect = addedRow.querySelector('.leg-depart');
                        const heuSelect = addedRow.querySelector('.leg-heure');
                        const arrSelect = addedRow.querySelector('.leg-arrivee');

                        ligSelect.value = prefill.lig_num;
                        peuplerArretsDepart(ligSelect.value, depSelect);
                        if (prefill.depart) {
                            depSelect.value = prefill.depart;
                            peuplerHoraires(ligSelect.value, depSelect.value, heuSelect, mDateValue());
                            if (prefill.heure) {
                                heuSelect.value = prefill.heure;
                            }
                            peuplerArretsArrivee(ligSelect.value, depSelect.value, arrSelect);
                            if (prefill.arrivee) {
                                arrSelect.value = prefill.arrivee;
                            }
                        }
                    }

                    updateRemoveButtons();
                }

                if (legsContainer) {
                    if (legsPrefill.length > 0) {
                        legsPrefill.forEach(leg => ajouterEtape(leg));
                    } else {
                        ajouterEtape();
                        ajouterEtape();
                    }

                    document.getElementById('add-leg-btn').addEventListener('click', () => ajouterEtape());
                }
            });
        </script>

    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
