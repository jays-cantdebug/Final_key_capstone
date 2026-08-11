@props(['user', 'size' => 'md'])

@php
    $sizeClasses = [
        'sm' => 'h-7 w-7 text-[10px]',
        'md' => 'h-11 w-11 text-sm',
        'lg' => 'h-16 w-16 text-lg',
        'xl' => 'h-20 w-20 text-xl',
    ][$size] ?? 'h-11 w-11 text-sm';
@endphp

@if ($user->avatar_path)
    <img
        src="{{ Storage::url($user->avatar_path) }}"
        alt="{{ $user->name }}"
        {{ $attributes->merge(['class' => "$sizeClasses rounded-full object-cover"]) }}
    >
@else
    <div
        {{ $attributes->merge(['class' => "$sizeClasses rounded-full bg-primary/15 text-primary font-semibold flex items-center justify-center"]) }}
        aria-hidden="true"
    >
        {{ $user->initials() }}
    </div>
@endif
