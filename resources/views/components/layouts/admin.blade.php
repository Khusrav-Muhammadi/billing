@props(['title' => null])
@include('admin.layout._shell-start', ['title' => $title])
{{ $slot }}
@include('admin.layout._shell-end')
