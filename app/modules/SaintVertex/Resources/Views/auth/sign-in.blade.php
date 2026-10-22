<?php
use App\Classes\Output;
use App\Events\AfterLoginFieldsEvent;
use App\Events\BeforeLoginFieldsEvent;
use App\Events\RenderLoginFooterEvent;
?>
@extends('layout.base')

@section('layout.base.body')
<main id="page-container" class="vtx-auth-page">
    <div class="vtx-auth-layout">
        <section class="vtx-auth-brand" aria-labelledby="vtx-product-title">
            <div class="vtx-brand-label">
                <img src="{{ asset('modules/saintvertex/vertex-mark.svg') }}" alt="" width="48" height="54">
                <span>SAINT VERTEX</span>
            </div>
            <p class="vtx-eyebrow">{{ __m('RUANG KERJA TOKO ANDA', 'SaintVertex') }}</p>
            <h1 id="vtx-product-title">Vertex <span>POS</span></h1>
            <p class="vtx-auth-description">{{ __m('Sistem point of sale modern untuk penjualan, inventaris, dan operasional toko.', 'SaintVertex') }}</p>
            <div class="vtx-capabilities" aria-label="{{ __m('Fitur utama', 'SaintVertex') }}">
                <div><i class="las la-shopping-bag" aria-hidden="true"></i><span>{{ __m('Penjualan', 'SaintVertex') }}</span></div>
                <div><i class="las la-boxes" aria-hidden="true"></i><span>{{ __m('Inventaris', 'SaintVertex') }}</span></div>
                <div><i class="las la-chart-bar" aria-hidden="true"></i><span>{{ __m('Laporan', 'SaintVertex') }}</span></div>
            </div>
            <p class="vtx-local-note"><span aria-hidden="true"></span>{{ __m('Dihosting lokal. Kendali ada di tangan Anda.', 'SaintVertex') }}</p>
        </section>

        <section class="vtx-auth-card" aria-labelledby="vtx-login-title">
            <div class="vtx-auth-card-heading">
                <p class="vtx-eyebrow">{{ __m('AKSES TOKO', 'SaintVertex') }}</p>
                <h2 id="vtx-login-title">{{ __m('Selamat datang kembali', 'SaintVertex') }}</h2>
                <p>{{ __m('Masuk untuk melanjutkan operasional toko Anda.', 'SaintVertex') }}</p>
            </div>
            <div id="sign-in-box">
                <x-session-message />
                {!! Output::dispatch(BeforeLoginFieldsEvent::class) !!}
                @include('common.auth.sign-in-form')
                {!! Output::dispatch(AfterLoginFieldsEvent::class) !!}
            </div>
            <p class="vtx-auth-card-note">{{ __m('Gunakan akun yang diberikan administrator toko.', 'SaintVertex') }}</p>
        </section>
    </div>
</main>
@endsection

@section('layout.base.footer')
    @parent
    {!! Output::dispatch(RenderLoginFooterEvent::class) !!}
    @include('SaintVertex::auth.assets')
@endsection
{{-- Saint Vertex presentation modifications: Copyright © 2026 Saint Vertex. Native auth components retain their ownership. --}}
