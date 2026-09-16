@extends('superadmin.layout.app')
@section('title', 'Select Programmes — ' . $onboarding->organization->name . ' — Superadmin')
@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('superadmin.onboarding.show', $onboarding->organization) }}" class="text-muted hover:text-text transition"><x-ui.icon name="arrow-left" class="h-5 w-5" /></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-text">Step 3: Select Programmes</h1>
            <p class="text-sm text-muted">{{ $onboarding->organization->name }} — Choose from NUC CCMAS baseline</p>
        </div>
    </div>
@endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-2xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-extrabold text-text">Onboarding Progress</h2>
            <div class="flex items-center gap-2">
                <div class="h-2.5 w-40 rounded-full bg-raised overflow-hidden">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: 60%"></div>
                </div>
                <span class="text-sm font-extrabold text-primary">60%</span>
            </div>
        </div>
        <div class="space-y-2">
            @php
                $steps = [
                    ['key'=>'institution_info','label'=>'Institution Setup','done'=>true],
                    ['key'=>'admin_profile','label'=>'Administrator Account','done'=>true],
                    ['key'=>'academic_structure','label'=>'Academic Structure (faculties / departments / programmes)','current'=>true],
                    ['key'=>'staff_setup','label'=>'Staff Setup','done'=>false],
                    ['key'=>'content_setup','label'=>'Content Setup','done'=>false],
                ];
            @endphp
            @foreach ($steps as $i => $step)
                @php $done = $step['done'] ?? false; $current = $step['current'] ?? false; @endphp
                <div class="flex items-center gap-4 rounded-xl px-4 py-3 {{ $done ? 'bg-emerald-50' : ($current ? 'bg-primary/5' : 'bg-raised') }}">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $done ? 'bg-emerald-500 text-white' : ($current ? 'bg-primary text-white' : 'bg-surface border border-border text-muted') }}">
                        @if ($done)
                            <x-ui.icon name="check" class="h-4 w-4" />
                        @elseif ($current)
                            <x-ui.icon name="academic-cap" class="h-4 w-4" />
                        @else
                            <span class="text-xs font-bold">{{ $i + 1 }}</span>
                        @endif
                    </div>
                    <span class="text-sm font-medium {{ $done ? 'text-emerald-800' : ($current ? 'text-primary' : 'text-muted') }}">{{ $step['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('superadmin.institutions.programmes.save', $onboarding->organization) }}" class="space-y-6">
        @csrf
        <input type="hidden" name="onboarding_step" value="academic_structure">

        <div class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-text">NUC CCMAS Programmes</h3>
                    <p class="text-sm text-muted mt-1">Select the programmes your institution offers. Faculties and departments will be created automatically.</p>
                </div>
                <span class="text-xs text-muted">{{ $selectedCount ?? 0 }} programmes selected</span>
            </div>

            <div class="space-y-4">
                @foreach ($disciplines as $disc)
                    @php
                        $discProgrammes = $programmes->where('nuc_discipline_id', $disc->id)->values();
                    @endphp
                    @if ($discProgrammes->isNotEmpty())
                        <div class="border border-border rounded-xl overflow-hidden">
                            <div class="bg-raised px-4 py-3 border-b border-border flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-text flex items-center gap-2">
                                    <x-ui.icon name="{{ $disc->icon ?? 'building-library' }}" class="h-4 w-4" />
                                    {{ $disc->name }} ({{ $disc->code }})
                                </h4>
                                <span class="text-xs text-muted">{{ $discProgrammes->count() }} programmes</span>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach ($discProgrammes as $programme)
                                    @php
                                        $isSelected = $selectedIds->contains($programme->id);
                                    @endphp
                                    <label class="flex items-center gap-3 p-3 rounded-xl border border-border {{ $isSelected ? 'bg-primary/5 ring-2 ring-primary/20' : 'hover:bg-raised' }} transition cursor-pointer">
                                        <input type="checkbox"
                                               name="programme_ids[]"
                                               value="{{ $programme->id }}"
                                               @if ($isSelected) checked @endif
                                               class="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/20">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-text truncate">{{ $programme->name }}</p>
                                            <p class="text-xs text-muted">{{ $programme->code }} · {{ $programme->duration_years }} years</p>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded-full bg-raised text-muted">{{ $programme->status }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flex gap-3 justify-end">
            <a href="{{ route('superadmin.onboarding.show', $onboarding->organization) }}" class="rounded-xl border border-border px-6 py-2.5 text-sm font-bold text-muted hover:bg-raised transition">Back</a>
            <button type="submit" class="rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-primary-fg transition hover:bg-primary/90">Save & Continue</button>
        </div>
    </form>
</div>
@endsection