@extends('superadmin.layout.app')
@section('title', 'Institution Create — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.institutions') }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <h1 class="text-xl font-extrabold tracking-tight text-text">Create Institution</h1>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-2xl">
    <form action="{{ route('superadmin.institutions.store') }}" method="POST" class="space-y-6">
        @csrf
        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-5">
            <h3 class="text-base font-extrabold text-text">Basic Information</h3>
            <div>
                <label for="name" class="block text-xs font-bold text-muted mb-1">Institution Name *</label>
                <input type="text" id="name" name="name" required value="{{ old('name') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="type" class="block text-xs font-bold text-muted mb-1">Institution Type *</label>
                    <select id="type" name="type" required class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        <option value="">Select type…</option>
                        @foreach (['university','federal','state','private','polytechnic','college','other'] as $type)
                            <option value="{{ $type }}" {{ old('type')===$type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="code" class="block text-xs font-bold text-muted mb-1">Institution Code</label>
                    <input type="text" id="code" name="code" value="{{ old('code') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="short_name" class="block text-xs font-bold text-muted mb-1">Short Name</label>
                    <input type="text" id="short_name" name="short_name" value="{{ old('short_name') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                </div>
                <div>
                    <label for="state" class="block text-xs font-bold text-muted mb-1">State</label>
                    <input type="text" id="state" name="state" value="{{ old('state') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                </div>
            </div>
            <div>
                <label for="website" class="block text-xs font-bold text-muted mb-1">Website</label>
                <input type="url" id="website" name="website" value="{{ old('website') }}" placeholder="https://…"
                       class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                @error('website')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-xs font-bold text-muted mb-1">Contact Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <div>
                <label for="address" class="block text-xs font-bold text-muted mb-1">Address</label>
                <input type="text" id="address" name="address" value="{{ old('address') }}" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </div>
            <div>
                <label for="description" class="block text-xs font-bold text-muted mb-1">Description</label>
                <textarea id="description" name="description" rows="3" class="w-full rounded-xl border border-border bg-raised px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">{{ old('description') }}</textarea>
            </div>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Create Institution</button>
            <a href="{{ route('superadmin.institutions') }}" class="rounded-xl border border-border px-6 py-2.5 text-sm font-bold text-muted hover:bg-raised transition">Cancel</a>
        </div>
    </form>
</div>
@endsection