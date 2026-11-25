@include('admin.layout._shell-start', ['title' => trim($__env->yieldContent('title'))])
<div class="bs">
@yield('content')
</div>
@yield('script')
@include('admin.layout._shell-end')
