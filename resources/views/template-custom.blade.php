{{--
  Template Name: Custom Template
--}}

@extends('layouts.app')

@section('content')
  @while(have_posts()) @php(the_post())
    @include('partials.page-header')
    @include('partials.content-page')
  @endwhile

  <div data-react="ExampleComponent" data-props='@json(['title' => __('React island', 'sage')])'>
    {{ __('Loading…', 'sage') }}
  </div>
@endsection
