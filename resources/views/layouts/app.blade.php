<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>@yield('title')</title>

    <link rel="stylesheet" href="{{asset('../assets/vendors/mdi/css/materialdesignicons.min.css')}}">
    <link rel="stylesheet" href="{{asset('../assets/vendors/flag-icon-css/css/flag-icon.min.css')}}">
    <link rel="stylesheet" href="{{asset('../assets/vendors/css/vendor.bundle.base.css')}}">

    <link rel="stylesheet" href="{{asset('../assets/vendors/jquery-bar-rating/css-stars.css')}}" />
    <link rel="stylesheet" href="{{asset('../assets/vendors/font-awesome/css/font-awesome.min.css')}}" />

    <link rel="stylesheet" href="{{asset('../assets/css/demo_1/style.css')}}" />

    <link rel="shortcut icon" href="{{asset('../assets/images/favicon.png')}}" />

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

</head>
<body>
<div class="container-scroller">

    <nav class="sidebar sidebar-offcanvas" id="sidebar">
        @include('incs.sidebar')
    </nav>

    <div class="container-fluid page-body-wrapper">
        <div id="settings-trigger"><i class="mdi mdi-settings"></i></div>
        <div id="theme-settings" class="settings-panel">
            <i class="settings-close mdi mdi-close"></i>
            <p class="settings-heading">SIDEBAR SKINS</p>
            <div class="sidebar-bg-options selected" id="sidebar-default-theme">
                <div class="img-ss rounded-circle bg-light border mr-3"></div>Default
            </div>
            <div class="sidebar-bg-options" id="sidebar-dark-theme">
                <div class="img-ss rounded-circle bg-dark border mr-3"></div>Dark
            </div>
            <p class="settings-heading mt-2">HEADER SKINS</p>
            <div class="color-tiles mx-0 px-4">
                <div class="tiles default primary"></div>
                <div class="tiles success"></div>
                <div class="tiles warning"></div>
                <div class="tiles danger"></div>
                <div class="tiles info"></div>
                <div class="tiles dark"></div>
                <div class="tiles light"></div>
            </div>
        </div>

        <nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="navbar-menu-wrapper d-flex align-items-stretch">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="mdi mdi-chevron-double-left"></span>
                </button>
                <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                    <a class="navbar-brand brand-logo-mini" href="#"><img src="{{asset('../assets/images/logo-mini.svg')}}" alt="logo" /></a>
                </div>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link" id="messageDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
                            <i class="mdi mdi-email-outline"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-left navbar-dropdown preview-list" aria-labelledby="messageDropdown">
                            <h6 class="p-3 mb-0 font-weight-semibold">Messages</h6>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <img src="{{asset('../assets/images/faces/face1.jpg')}}" alt="image" class="profile-pic">
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject ellipsis mb-1 font-weight-normal">Mark send you a message</h6>
                                    <p class="text-gray mb-0"> 1 Minutes ago </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <img src="{{asset('../assets/images/faces/face6.jpg')}}" alt="image" class="profile-pic">
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject ellipsis mb-1 font-weight-normal">Cregh send you a message</h6>
                                    <p class="text-gray mb-0"> 15 Minutes ago </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <img src="{{asset('../assets/images/faces/face7.jpg')}}" alt="image" class="profile-pic">
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject ellipsis mb-1 font-weight-normal">Profile picture updated</h6>
                                    <p class="text-gray mb-0"> 18 Minutes ago </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <h6 class="p-3 mb-0 text-center text-primary font-13">4 new messages</h6>
                        </div>
                    </li>
                    <li class="nav-item dropdown ml-3">
                        <a class="nav-link" id="notificationDropdown" href="#" data-toggle="dropdown">
                            <i class="mdi mdi-bell-outline"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-left navbar-dropdown preview-list" aria-labelledby="notificationDropdown">
                            <h6 class="px-3 py-3 font-weight-semibold mb-0">Notifications</h6>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <div class="preview-icon bg-success">
                                        <i class="mdi mdi-calendar"></i>
                                    </div>
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject font-weight-normal mb-0">New order recieved</h6>
                                    <p class="text-gray ellipsis mb-0"> 45 sec ago </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <div class="preview-icon bg-warning">
                                        <i class="mdi mdi-settings"></i>
                                    </div>
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject font-weight-normal mb-0">Server limit reached</h6>
                                    <p class="text-gray ellipsis mb-0"> 55 sec ago </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item preview-item">
                                <div class="preview-thumbnail">
                                    <div class="preview-icon bg-info">
                                        <i class="mdi mdi-link-variant"></i>
                                    </div>
                                </div>
                                <div class="preview-item-content d-flex align-items-start flex-column justify-content-center">
                                    <h6 class="preview-subject font-weight-normal mb-0">Kevin karvelle</h6>
                                    <p class="text-gray ellipsis mb-0"> 11:09 PM </p>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <h6 class="p-3 font-13 mb-0 text-primary text-center">View all notifications</h6>
                        </div>
                    </li>
                </ul>
                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item nav-logout d-none d-md-block mr-3">
                        <a class="nav-link" href="#">Status</a>
                    </li>
                    <li class="nav-item nav-logout d-none d-md-block">
                        <button class="btn btn-sm btn-danger">Trailing</button>
                    </li>
                    <li class="nav-item nav-profile dropdown d-none d-md-block">
                        <a class="nav-link dropdown-toggle" id="profileDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
                            <div class="nav-profile-text">English </div>
                        </a>
                        <div class="dropdown-menu center navbar-dropdown" aria-labelledby="profileDropdown">
                            <a class="dropdown-item" href="#">
                                <i class="flag-icon flag-icon-bl mr-3"></i> French </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">
                                <i class="flag-icon flag-icon-cn mr-3"></i> Chinese </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">
                                <i class="flag-icon flag-icon-de mr-3"></i> German </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">
                                <i class="flag-icon flag-icon-ru mr-3"></i>Russian </a>
                        </div>
                    </li>
                    <li class="nav-item nav-logout d-none d-lg-block">
                        <a class="nav-link" href="index.html">
                            <i class="mdi mdi-home-circle"></i>
                        </a>
                    </li>
                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                    <span class="mdi mdi-menu"></span>
                </button>
            </div>
        </nav>

        <div class="main-panel">
            <div class="content-wrapper pb-0">
                @yield('content')
            </div>

            @include('incs.footer')

        </div>

    </div>

</div>

<script src="{{asset('../assets/vendors/js/vendor.bundle.base.js')}}"></script>

<script src="{{asset('../assets/vendors/jquery-bar-rating/jquery.barrating.min.js')}}"></script>
<script src="{{asset('../assets/vendors/chart.js/Chart.min.js')}}"></script>
<script src="{{asset('../assets/vendors/flot/jquery.flot.js')}}"></script>
<script src="{{asset('../assets/vendors/flot/jquery.flot.resize.js')}}"></script>
<script src="{{asset('../assets/vendors/flot/jquery.flot.categories.js')}}"></script>
<script src="{{asset('../assets/vendors/flot/jquery.flot.fillbetween.js')}}"></script>
<script src="{{asset('../assets/vendors/flot/jquery.flot.stack.js')}}"></script>

<script src="{{asset('../assets/js/off-canvas.js')}}"></script>
<script src="{{asset('../assets/js/hoverable-collapse.js')}}"></script>
<script src="{{asset('../assets/js/misc.js')}}"></script>
<script src="{{asset('../assets/js/settings.js')}}"></script>
<script src="{{asset('../assets/js/todolist.js')}}"></script>

<script src="{{asset('../assets/js/dashboard.js')}}"></script>

</body>
</html>
