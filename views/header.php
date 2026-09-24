<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../includes/functions.php';
if (!is_ip_whitelisted()) {
    http_response_code(403);
    die("Access Denied: This system is restricted to local server access only. External access via network IP is prohibited.");
}

ob_start();
require_once __DIR__ . '/../config/database.php';


header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://ui-avatars.com https://images.unsplash.com; connect-src 'self';");


check_session_timeout(30);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Management System</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('assets/css/modern-ui.css'); ?>">
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    <script src="<?php echo asset_url('assets/js/toast.js'); ?>"></script>
    <?php echo render_toasts(); ?>

    <div class="modal fade" id="privacyNoticeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="privacyNoticeLabel" aria-hidden="true" style="z-index: 2000;">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="background:#1e293b; color:#e2e8f0;">
                <div class="modal-header border-secondary border-opacity-25" style="background:#0f172a;">
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="privacyNoticeLabel">PAUNAWA SA PRIBASIDAD</h5>
                        <small class="text-secondary">Privacy Notice</small>
                    </div>
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25">Taguig City General Hospital</span>
                </div>
                <div class="modal-body" style="font-size:0.9rem; line-height:1.6;">
                    <p class="text-secondary small fst-italic mb-3">Para sa mga Pasyente</p>
                    <p>Pinahahalagahan, iginagalang, at aming pinoprotektahan ang inyong personal at sensitibong impormasyong pangkalusugan. Ang lahat ng impormasyong aming kinokolekta at pinoproseso ay alinsunod sa Batas Republika Blg. 10173, na kilala bilang Data Privacy Act (DPA), at sa mga kaugnay na patakaran at regulasyon.</p>

                    <h6 class="fw-bold text-white mt-3">Mga Datos na Aming Kinokolekta</h6>
                    <p>Ang Taguig City General Hospital Appointment System ay nangongolekta at nagpoproseso ng sumusunod na personal at sensitibong personal na impormasyon:</p>
                    <ul class="mb-2">
                        <li>Buong pangalan</li>
                        <li>Petsa ng kapanganakan</li>
                        <li>Kasarian</li>
                        <li>Tirahan</li>
                        <li>Mga detalye sa pakikipag-ugnayan (mobile number at/o email address)</li>
                        <li>Mga dokumentong medikal na isinumite para sa konsultasyon</li>
                        <li>Mga detalye ng appointment</li>
                        <li>Mga kaugnay na kondisyong medikal at impormasyon sa konsultasyon</li>
                    </ul>

                    <h6 class="fw-bold text-white mt-3">Layunin ng Pagproseso ng Impormasyon</h6>
                    <ul class="mb-2">
                        <li>Upang matiyak ang tamang pagkakakilanlan ng bawat pasyente</li>
                        <li>Upang magsagawa ng wastong pagsusuri at matukoy ang kalagayang pangkalusugan ng pasyente</li>
                        <li>Upang makapagbigay ng tama, angkop, at napapanahong gamutan</li>
                        <li>Upang maproseso ang mga laboratory request at referral</li>
                        <li>Upang sumunod sa mga kinakailangan ng Department of Health (DOH), PhilHealth, City Government of Taguig, at iba pang umiiral na batas at regulasyon</li>
                        <li>Upang mapabuti ang kalidad ng serbisyong pangkalusugan at mga programang pangkalusugan</li>
                    </ul>

                    <h6 class="fw-bold text-white mt-3">Legal na Batayan ng Pagproseso ng Datos</h6>
                    <ul class="mb-2">
                        <li>Ang inyong pahintulot</li>
                        <li>Ang pangangailangan para sa pagbibigay ng serbisyong pangkalusugan</li>
                        <li>Ang pagsunod sa mga legal na obligasyon ng isang lisensyadong pasilidad pangkalusugan</li>
                        <li>Ang pagsasagawa ng mga tungkuling may kinalaman sa pampublikong interes, kabilang ang pagmanman sa pampublikong kalusugan (public health surveillance)</li>
                        <li>Ang pagsunod sa mga umiiral na batas, alituntunin, at regulasyon</li>
                    </ul>

                    <h6 class="fw-bold text-white mt-3">Pagbibigay o Pagsisiwalat ng Personal na Datos</h6>
                    <ul class="mb-2">
                        <li>Mga doktor at iba pang healthcare providers na direktang kabilang sa inyong gamutan</li>
                        <li>Mga nirerefer na klinika, ospital, o diagnostic facilities</li>
                        <li>Mga ahensya ng pamahalaan, awtoridad sa pampublikong kalusugan, at regulatory bodies, kung ito ay hinihingi ng batas</li>
                        <li>Iba pang tanggapan sa loob ng City Government of Taguig, para sa pagproseso ng mga kahilingan para sa serbisyo o tulong, at para sa pagbabalangkas ng mga polisiya</li>
                    </ul>

                    <h6 class="fw-bold text-white mt-3">Proteksyon at Seguridad ng Datos</h6>
                    <ul class="mb-2">
                        <li>Limitado at kontroladong access sa mga rekord at impormasyon</li>
                        <li>Ligtas na imbakan (storage) at pag-encrypt ng mga elektronikong rekord</li>
                        <li>Regular na pagba-backup ng datos at monitoring</li>
                    </ul>

                    <h6 class="fw-bold text-white mt-3">Panahon ng Pagpapanatili at Disposal ng mga Rekord</h6>
                    <p>Ang pagpapanatili at disposal ng mga rekord ay susunod sa General Records Disposition Schedule na karaniwan sa mga Local Government Units. Ang disposal ng mga rekord ay isasagawa alinsunod sa Disposal Policy na nakapaloob sa Privacy Manual ng Pamahalaang Lungsod Taguig, at kinakailangan ng paunang pahintulot mula sa Pambansang Sinupan ng Pilipinas.</p>

                    <h6 class="fw-bold text-white mt-3">Mga Karapatan ng Data Subject at Proseso ng Paghingi</h6>
                    <p>Mayroon kayong karapatang:</p>
                    <ul class="mb-2">
                        <li>Maabisuhan (right to be informed)</li>
                        <li>Tumutol (right to object)</li>
                        <li>Magkaroon ng access sa inyong datos</li>
                        <li>Ipaayos o iwasto ang maling impormasyon (right to rectification)</li>
                        <li>Ipabura o ipa-block ang datos (right to erasure or blocking)</li>
                        <li>Humiling ng data portability</li>
                        <li>Maghabol ng danyos</li>
                        <li>Magsampa ng reklamo sa National Privacy Commission (NPC) kaugnay ng pagproseso ng inyong personal na datos</li>
                    </ul>
                    <p>Upang magamit ang alinman sa mga karapatang ito, maaari kayong magsumite ng nakasulat na kahilingan sa Data Privacy Officer, na naglalaman ng mga sumusunod:</p>
                    <ul class="mb-2">
                        <li>Buong pangalan at contact details</li>
                        <li>Malinaw na paglalarawan ng karapatang nais ninyong gamitin</li>
                        <li>Kopya ng isang valid ID na inisyu ng gobyerno</li>
                        <li>Iba pang kaugnay na dokumento, kung kinakailangan</li>
                    </ul>
                    <p class="mb-2">Paalala na ang ilang kahilingan ay maaaring may limitasyon alinsunod sa umiiral na mga batas, tuntunin, at regulasyon, o kung ang pagproseso ng datos ay kinakailangan para sa pagtupad ng isang legal na obligasyon o pagsasagawa ng opisyal na awtoridad.</p>

                    <h6 class="fw-bold text-white mt-3">Impormasyon sa Pakikipag-ugnayan</h6>
                    <p class="mb-1">Taguig City General Hospital<br>C6 Road, Brgy. Hagonoy, Lungsod Taguig<br>tgh@taguig.gov.ph</p>
                    <p class="mb-0">Data Protection Officer (DPO)<br>Email: tcghdpo@taguig.gov.ph</p>
                </div>
                <div class="modal-footer border-secondary border-opacity-25" style="background:#0f172a;">
                    <button type="button" class="btn btn-primary px-4" id="privacyAcceptBtn" data-bs-dismiss="modal">Naintindihan ko (I Understand)</button>
                </div>
            </div>
        </div>
    </div>

    <div id="global-loader">
        <div class="cyber-loader">
            <div class="cyber-hex"></div>
            <div class="cyber-inner"></div>
        </div>
        <div class="loader-text">System <span>Initializing</span>...</div>
    </div>
    <script>
        window.addEventListener('load', function () {
            const loader = document.getElementById('global-loader');
            setTimeout(() => {
                loader.classList.add('loaded');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500);
            }, 800);
        });
    </script>
    <div class="wrapper">
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <nav
                class="navbar navbar-expand navbar-dark bg-transparent py-3 px-4 border-bottom border-secondary border-opacity-10 backdrop-blur-md">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-icon btn-sm btn-ghost-secondary d-md-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="d-none d-md-block">
                            <span id="realtime-clock" class="text-secondary small text-uppercase fw-bold"
                                style="letter-spacing: 0.05em; font-variant-numeric: tabular-nums;"></span>
                        </div>
                        <script>
                            const APP_TIMEZONE = <?php echo json_encode(env('APP_TIMEZONE', 'Asia/Manila')); ?>;
                            function updateClock() {
                            const options = {
                                timeZone: APP_TIMEZONE,
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                                hour12: false
                            };
                                const formatter = new Intl.DateTimeFormat('en-US', options);
                                document.getElementById('realtime-clock').textContent = formatter.format(new Date());
                            }
                            setInterval(updateClock, 1000);
                            updateClock(); 
                        </script>
                    </div>


                    <div class="d-flex align-items-center gap-3">

                        <div class="dropdown">
                            <button class="btn btn-icon btn-sm btn-ghost-secondary position-relative me-1"
                                id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span id="notif-badge"
                                    class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-dark rounded-circle ms-n2 mt-1 d-none">
                                    <span class="visually-hidden">New alerts</span>
                                    <span id="notif-count"></span>
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-dark bg-dark border-secondary border-opacity-25 shadow-lg py-0 mt-2"
                                aria-labelledby="notificationDropdown" style="width: 300px;">
                                <div
                                    class="px-3 py-2 border-bottom border-secondary border-opacity-25 bg-secondary bg-opacity-10 d-flex justify-content-between align-items-center">
                                    <h6 class="small fw-bold text-white mb-0">Notifications</h6>
                                    <button id="markAllRead" class="btn btn-xs text-primary p-0 border-0 bg-transparent" style="font-size: 0.7rem;">Mark all read</button>
                                </div>
                                <div id="notif-list" class="p-2" style="max-height: 320px; overflow-y: auto;">
                                    <div class="p-4 text-center">
                                        <div class="text-secondary opacity-25 mb-2"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
                                        <p class="text-secondary small mb-0">Loading...</p>
                                    </div>
                                </div>
                                <div
                                    class="px-3 py-2 border-top border-secondary border-opacity-25 bg-secondary bg-opacity-10 text-center">
                                    <a href="#" class="small text-decoration-none text-primary hover-text-white">View all</a>
                                </div>
                            </div>
                        </div>

                        <div class="vr bg-secondary opacity-25 mx-1" style="height: 24px;"></div>


                        <?php if (is_logged_in()): ?>
                            <div class="dropdown">
                                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle no-caret"
                                    id="topUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="d-none d-md-block text-end me-3">
                                        <div class="text-white fw-medium small">
                                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
                                        </div>
                                        <div class="text-secondary text-xs opacity-75" style="font-size: 0.7rem;">
                                            <?php echo ucfirst($_SESSION['role'] ?? 'User'); ?>
                                        </div>
                                    </span>
                                    <div class="avatar-circle bg-primary bg-opacity-25 text-primary rounded-circle d-flex align-items-center justify-content-center border border-primary border-opacity-25"
                                        style="width: 38px; height: 38px;">
                                        <?php echo substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark bg-dark border-secondary border-opacity-25 shadow-lg py-1 mt-2"
                                    aria-labelledby="topUserDropdown">
                                    <li><a class="dropdown-item small py-2" href="profile.php"><i
                                                class="fas fa-user-circle me-2 opacity-50"></i> My Profile</a></li>
                                    <li><a class="dropdown-item small py-2" href="settings.php"><i
                                                class="fas fa-cog me-2 opacity-50"></i> Settings</a></li>
                                    <li>
                                        <hr class="dropdown-divider bg-secondary opacity-25 my-1">
                                    </li>
                                    <li><a class="dropdown-item small text-danger py-2"
                                            href="/IT Management System/public/logout.php"><i
                                                class="fas fa-sign-out-alt me-2 opacity-50"></i> Logout</a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
            <div class="content p-4">