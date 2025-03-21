<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

    <link rel="stylesheet" href="{{asset('../assets/vendors/mdi/css/materialdesignicons.min.css')}}">
    <link rel="stylesheet" href="{{asset('../assets/vendors/flag-icon-css/css/flag-icon.min.css')}}">
    <link rel="stylesheet" href="{{asset('../assets/vendors/css/vendor.bundle.base.css')}}">

    <link rel="stylesheet" href="{{asset('../assets/vendors/jquery-bar-rating/css-stars.css')}}" />
    <link rel="stylesheet" href="{{asset('../assets/vendors/font-awesome/css/font-awesome.min.css')}}" />

    <link rel="stylesheet" href="{{asset('../assets/css/demo_1/style.css')}}" />

    <link rel="shortcut icon" href="{{asset('../assets/images/favicon.png')}}" />
    <link rel="stylesheet" href="{{asset('style.css')}}">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

</head>
<body>
<div class="container-scroller">

    <nav class="sidebar sidebar-offcanvas" id="sidebar">
        @include('incs.sidebar')
    </nav>

    <div class="container-fluid page-body-wrapper">
        <nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="navbar-menu-wrapper d-flex align-items-stretch">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="mdi mdi-chevron-double-left"></span>
                </button>
                <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                    <a class="navbar-brand brand-logo-mini" href="#"><img src="{{asset('../assets/images/logo-mini.svg')}}" alt="logo" /></a>
                </div>
                <ul class="navbar-nav">
                </ul>
                <ul class="navbar-nav navbar-nav-right">
                    <li class="nav-item nav-logout d-none d-lg-block">
                        <a class="nav-link" href="{{ route('profile.index') }}">
                            <i class="mdi mdi-account-circle"></i>
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
@yield('script')
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
