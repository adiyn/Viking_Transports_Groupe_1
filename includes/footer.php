        <footer class="mt-auto pt-5 pb-4 border-top" style="background-color: #3a1f1f; border-color: #7a4848 !important;">
            <div class="container">
                <div class="row gy-4 align-items-center">
                    
                    <div class="col-md-6 text-center text-md-start">
                        <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-3 mb-3">
                            
                            <!-- Conteneur circulaire avec un padding interne pour éviter que l'image touche le bord -->
                            <div class="shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" 
                                style="width: 80px; height: 80px; border-radius: 50%; background-color: #ffffff;
                                overflow: hidden; border: 2px solid #7a4848; padding: 10px;">
                                <img src="../public/img/logo_blanc.png" alt="Viking Logo" 
                                    style="width: 100%; height: 100%; object-fit: contain; object-position: center; 
                                    transition: transform 0.3s ease;"
                                    onmouseover="this.style.transform='scale(1.1)'" 
                                    onmouseout="this.style.transform='scale(1.0)'">
                            </div>
                            
                            <span class="fs-4 fw-bold text-white text-uppercase" style="letter-spacing: 1px;">
                                Viking <span style="color: #ba181b;">Transport</span>
                            </span>
                        </div>
                        <p class="text-white-50 small mb-1 font-monospace">
                            &copy; 2026 Tantu Team. Tous droits réservés.
                        </p>
                        <p class="text-white-50 small mb-0" style="font-size: 0.8rem;">
                            Conception & développement par l'équipe Tantu Inc.<br>
                            Campus de Caen.
                        </p>
                    </div>
                    
                    <div class="col-md-6 text-center text-md-end">
                        <div class="mb-3">
                            <span class="d-block text-uppercase fw-bold mb-1" style="color: #9e7b7b; 
                                font-size: 0.7rem; letter-spacing: 1.5px;">Contact Réseau Viking</span>
                            <a href="mailto:viking.transports@proton.me" class="text-white text-decoration-none 
                                fw-medium link-danger" style="transition: color 0.2s ease;">
                                <i class="bi bi-envelope-fill me-2" style="color: #ba181b;"></i>viking.transports@proton.me
                            </a>
                        </div>
                        <div>
                            <span class="d-block text-uppercase fw-bold mb-1" style="color: #9e7b7b; 
                                font-size: 0.7rem; letter-spacing: 1.5px;">Support Technique Tantu</span>
                            <a href="mailto:Tantu.Inc@proton.me" class="text-white text-decoration-none 
                                fw-medium link-danger" style="transition: color 0.2s ease;">
                                <i class="bi bi-headset me-2" style="color: #ba181b;"></i>Tantu.Inc@proton.me
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // c opti trust (nn sah ça ralentit rien il fait quasi r mdr)
            window.addEventListener('load', function() {
                // lancement en bg avec fethc
                fetch('/frontend/pseudocron.php')
                    .catch(error => { /* tu veux savoir ce qu'il s'est passé ? bah ntm :D */});
            });
        </script>
    </body>
</html>