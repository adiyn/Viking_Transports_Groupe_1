<?php
include_once __DIR__ . "/../includes/header.php";
include_once __DIR__ . "/../config/pdo_agile.php";
include_once __DIR__ . "/../config/param_connexion.php";

$_SESSION['current_page'] = 'lignes.php'; // Stocker la page actuelle pour redirection après connexion


$db_username = $db_usernameOracle;
$db_password = $db_passwordOracle;
$db = $dbOracle;

$selected = isset($_GET['ligne']) ? trim((string) $_GET['ligne']) : '1A';

$conn = OuvrirConnexionPDO($db, $db_username, $db_password);

if ($conn === null) {
    die("Erreur: Impossible de se connecter à la base de données.");
}

$lignes = [];
LireDonneesPDO1($conn, "
    SELECT n.LIG_NUM, c1.COM_NOM AS COM_DEPART, c2.COM_NOM AS COM_ARRIVEE
    FROM (
        SELECT TRIM(LIG_NUM) AS LIG_NUM,
               MIN(COM_CODE_INSEE_ARRET) KEEP (DENSE_RANK FIRST ORDER BY NOE_HEURE_PASSAGE) AS CODE_DEP,
               MAX(COM_CODE_INSEE_SUIVANT) KEEP (DENSE_RANK LAST ORDER BY NOE_HEURE_PASSAGE + NVL(NOE_DUREE_PROCHAIN, 0) / 1440) AS CODE_ARR
        FROM VIK_NOEUD
        GROUP BY TRIM(LIG_NUM)
    ) n
    JOIN VIK_COMMUNE c1 ON c1.COM_CODE_INSEE = n.CODE_DEP
    JOIN VIK_COMMUNE c2 ON c2.COM_CODE_INSEE = n.CODE_ARR
    ORDER BY TO_NUMBER(REGEXP_SUBSTR(n.LIG_NUM, '\d+')), REGEXP_SUBSTR(n.LIG_NUM, '[A-Z]+')
", $lignes);

$lignes_groupees = [];
foreach ($lignes as $ligne) {
    $num = preg_replace('/[^0-9]/', '', trim($ligne['LIG_NUM']));
    $lignes_groupees[$num][] = $ligne;
}

$arrets = [];
$sqlArrets = "
    SELECT com_code_insee, com_nom FROM 
(
    SELECT com_code_insee,com_nom,MIN(ordre) ordre FROM 
    (
        SELECT co.com_code_insee,co.com_nom,no.noe_heure_passage AS ordre
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_arret
        WHERE TRIM(no.lig_num) = :ligne
        UNION ALL
        SELECT co.com_code_insee,co.com_nom,no.noe_heure_passage + (no.noe_duree_prochain/1440)
        FROM vik_noeud no
        JOIN vik_commune co ON co.com_code_insee = no.com_code_insee_suivant
        WHERE TRIM(no.lig_num) = :ligne
    )
    GROUP BY com_code_insee, com_nom
)
ORDER BY ordre
";
$curArrets = preparerRequetePDO($conn, $sqlArrets);
$curArrets->bindValue(':ligne', trim($selected), PDO::PARAM_STR);
LireDonneesPDOPreparee($curArrets, $arrets);
$nbArrets = count($arrets);
?>

<meta charset="UTF-8">

<div class="container-fluid px-4 mt-4">

  <div class="mb-4">
    <h1 class="text-center fs-3 fw-bold mb-1" style="color: #111111;">Lignes & arrêts</h1>
    <p class="text-center text-muted mb-0 medium">Sélectionnez une ligne pour visualiser ses communes desservies.</p>
  </div>

  <?php $estConnecte = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true; ?>
  <div class="row g-3" style="min-height: calc(100vh - 160px);">

    <div class="col-md-3 col-lg-2">
      <div class="card border rounded-4 p-3 bg-white shadow-sm h-100 d-flex flex-column">
        <p class="text-uppercase text-muted small fw-bold mb-3">
          <i class="bi bi-compass me-2 text-danger"></i>Itinéraire
        </p>
        <select class="form-select form-select-sm rounded-3 fw-semibold py-2"
                onchange="window.location.href='lignes.php?ligne=' + this.value;">
          <?php foreach ($lignes_groupees as $num => $groupe): ?>
            <optgroup label="— Ligne <?= $num ?> —">
              <?php foreach ($groupe as $ligne): ?>
                <option value="<?= htmlspecialchars($ligne['LIG_NUM']) ?>"
                  <?= trim($ligne['LIG_NUM']) == trim($selected) ? 'selected' : '' ?>>
                  <?= htmlspecialchars(trim($ligne['LIG_NUM'])) ?> · <?= htmlspecialchars($ligne['COM_DEPART']) ?> → <?= htmlspecialchars($ligne['COM_ARRIVEE']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
          <span class="text-muted small fw-medium">Arrêts desservis :</span>
          <span class="badge rounded-pill fs-6 px-3 py-1 text-white" style="background-color: #ba181b;"><?= $nbArrets ?></span>
        </div>

        <div class="mt-3 pt-3 border-top">
          <a href="horaire.php?ligne=<?= $selected ?>"
             class="btn btn-outline-dark btn-sm w-100 rounded-pill d-flex align-items-center justify-content-center gap-2 py-2 fw-bold">
            <i class="bi bi-clock-history"></i> Grille Horaires
          </a>
        </div>

        <div class="mt-3 pt-3 border-top d-flex flex-column flex-grow-1" style="min-height: 0;">
          <p class="text-uppercase text-muted small fw-bold mb-2 flex-shrink-0">
            <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Parcours
          </p>
          <ul class="list-unstyled mb-0 overflow-y-auto flex-grow-1" style="min-height: 0;">
            <?php foreach ($arrets as $arret): ?>
              <li class="d-flex align-items-center gap-3 py-2 border-bottom text-dark fw-medium" style="font-size: 0.85rem;">
                <span style="width:8px; height:8px; border-radius:50%; background:#ba181b; flex-shrink:0;"></span>
                <?= htmlspecialchars($arret['COM_NOM']) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="<?= $estConnecte ? 'col-md-5 col-lg-6' : 'col-md-6 col-lg-7' ?>">
      <div class="card border rounded-4 overflow-hidden p-0 h-100 shadow-sm" style="min-height: 500px;">
        <img class=""
             src="../public/img/carte.png"
             alt="Carte de la ligne <?= htmlspecialchars($selected) ?>"
             style="height:auto;width:100%;">
      </div>
    </div>

    <div class="<?= $estConnecte ? 'col-md-4 col-lg-4' : 'col-md-3 col-lg-3' ?>">
      <div class="card border rounded-4 bg-white shadow-sm <?= $estConnecte ? 'h-100' : '' ?> overflow-hidden" style="position:relative;">
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
          <div class="p-3 h-100 overflow-y-auto">
            <?php include __DIR__ . '/../includes/itineraire.php'; ?>
          </div>
        <?php else: ?>

          <!-- PUB SUBWAY SURFERS -->
          <div id="subway-ad" style="position:relative; width:100%; aspect-ratio:9/16; overflow:hidden; cursor:default;">
            <img src="../public/gif/SubwayGif.gif" alt=""
                 style="width:100%; height:100%; object-fit:cover; display:block;">
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none;">
              <p style="color:#ffff00; font-size:1.15rem; font-weight:900; -webkit-text-stroke:2px #000; text-stroke:2px #000; text-align:center; padding:0 1rem; transform:rotate(-5deg); letter-spacing:2px; font-family:Impact, sans-serif; line-height:1.6;">
                🎉 <span style="color:#ff69b4;">F</span>EL<span style="color:#ff69b4;">I</span>CI<span style="color:#ff69b4;">T</span>ATI<span style="color:#ff69b4;">O</span>NS !! 🎉<br>
                V<span style="color:#ff69b4;">O</span>US E<span style="color:#ff69b4;">T</span>ES LE V<span style="color:#ff69b4;">I</span>SITEUR<br>
                N°<span style="color:#ff69b4;">1</span> 000 <span style="color:#ff69b4;">0</span>00 !!!!<br>
                🏆 P<span style="color:#ff69b4;">R</span>IX G<span style="color:#ff69b4;">A</span>RANTI 🏆<br>
                <span style="font-size:0.8rem; letter-spacing:1px;">C<span style="color:#ff69b4;">l</span>iquez m<span style="color:#ff69b4;">a</span>intenant p<span style="color:#ff69b4;">o</span>ur r<span style="color:#ff69b4;">é</span>clamer</span>
              </p>
            </div>
            <!-- flèche vers la croix -->
            <i class="bi bi-arrow-up-right" style="position:absolute; top:46px; right:46px; font-size:2.8rem; color:#ba181b; pointer-events:none; filter:drop-shadow(0 0 4px rgba(0,0,0,0.9)) drop-shadow(0 0 2px #000); font-weight:900;"></i>
            <button onclick="document.getElementById('subway-ad').style.display='none'; document.getElementById('login-msg').style.display='flex';"
                    style="position:absolute; top:8px; right:8px; background:rgba(0,0,0,0.55); border:none; color:#fff; font-size:1rem; width:28px; height:28px; border-radius:50%; cursor:pointer; line-height:1; display:flex; align-items:center; justify-content:center;">
              ✕
            </button>
          </div>

          <!-- MESSAGE CONNEXION (caché par défaut) -->
          <div id="login-msg" style="display:none; flex-direction:column; align-items:center; justify-content:center; height:100%; min-height:500px; padding:2rem; text-align:center;">
            <i class="bi bi-lock-fill mb-3" style="font-size:3rem; color:#ba181b;"></i>
            <h5 class="fw-bold mb-2">Accès restreint</h5>
            <p class="text-muted mb-4" style="font-size:0.9rem;">
              La recherche d'itinéraires est réservée aux clients. Une connexion est requise pour accéder à cette fonctionnalité.
            </p>
            <div class="d-flex flex-column gap-2 w-100" style="max-width:220px;">
              <a href="/frontend/connexion.php" class="btn btn-sm fw-bold rounded-pill text-white" style="background-color:#ba181b;">
                <i class="bi bi-shield-lock-fill me-1"></i> Se connecter
              </a>
              <a href="/frontend/inscription.php" class="btn btn-sm fw-bold rounded-pill btn-outline-secondary">
                <i class="bi bi-person-plus me-1"></i> Créer un compte
              </a>
            </div>
          </div>

        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
