@php
$labelClass ??= '';
$userCardClass ??= '';
$linkJustifyClass ??= 'justify-start';

$navLinkClasses = fn (bool $active) => $active
    ? "flex items-center gap-3 rounded-lg bg-tint px-4 py-3 text-sm font-semibold text-primary shadow-sm $linkJustifyClass"
    : "group flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-medium text-white/85 transition hover:bg-tint hover:text-primary $linkJustifyClass";

$iconClasses = fn (bool $active) => $active ? 'h-5 w-5 flex-shrink-0 text-primary' : 'h-5 w-5 flex-shrink-0 text-white/70 transition group-hover:text-primary';
@endphp

<div class="{{ $userCardClass }} rounded-lg border border-white/10 bg-white/10 px-4 py-4 backdrop-blur">
    <p class="text-xs uppercase tracking-[0.3em] text-white/60">Signed in as</p>
    <p class="mt-2 text-sm font-semibold">{{ auth()->user()->name }}</p>
    <p class="text-xs text-white/75">{{ auth()->user()->role?->display_name ?? 'Unassigned Role' }}</p>
</div>

<nav class="flex flex-1 flex-col">
    <ul role="list" class="flex flex-1 flex-col gap-y-2">
        <li>
            <a href="{{ route('dashboard') }}" class="{{ $navLinkClasses(request()->routeIs('dashboard') || request()->routeIs('*.dashboard')) }}">
                <svg class="{{ $iconClasses(request()->routeIs('dashboard') || request()->routeIs('*.dashboard')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2 2 9h2v9h4v-5h4v5h4V9h2L10 2Z" /></svg>
                <span class="{{ $labelClass }}">Dashboard</span>
            </a>
        </li>
        @if(auth()->user()->hasRole('psychometrician'))
            <li>
                <a href="{{ route('assessments.create') }}" class="{{ $navLinkClasses(request()->routeIs('assessments.create*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('assessments.create*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M6 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7.414A2 2 0 0 0 15.414 6L11 1.586A2 2 0 0 0 9.586 1H6Zm4 8a1 1 0 0 1 1 1v1h1a1 1 0 1 1 0 2h-1v1a1 1 0 1 1-2 0v-1H8a1 1 0 1 1 0-2h1v-1a1 1 0 0 1 1-1Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">New Assessment</span>
                </a>
            </li>
            <li>
                <a href="{{ route('students.index') }}" class="{{ $navLinkClasses(request()->routeIs('students.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('students.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a3 3 0 0 0-3 3v1H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-2V5a3 3 0 0 0-3-3Zm1 4V5a1 1 0 1 0-2 0v1h2Z" /></svg>
                    <span class="{{ $labelClass }}">Students</span>
                </a>
            </li>
            <li>
                <a href="{{ route('questionnaires.index') }}" class="{{ $navLinkClasses(request()->routeIs('questionnaires.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('questionnaires.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h5.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm7 7a1 1 0 1 0-2 0v.01a1 1 0 0 0 2 0V11Zm-1-4a1 1 0 0 1 1 1v1a1 1 0 1 1-2 0V8a1 1 0 0 1 1-1Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Questions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="{{ $navLinkClasses(request()->routeIs('reports.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('reports.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7.414a1 1 0 0 0-.293-.707l-3.414-3.414A1 1 0 0 0 12.586 3H4Zm2 9a1 1 0 1 1 0-2h8a1 1 0 1 1 0 2H6Zm0-4a1 1 0 0 1 0-2h4a1 1 0 1 1 0 2H6Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Reports</span>
                </a>
            </li>
            <li>
                <a href="{{ route('audit-logs.index') }}" class="{{ $navLinkClasses(request()->routeIs('audit-logs.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('audit-logs.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h5.586A2 2 0 0 1 13 2.586L16.414 6A2 2 0 0 1 17 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm3 8a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Zm0-3a1 1 0 0 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Audit Logs</span>
                </a>
            </li>
            <li>
                <a href="{{ route('settings.edit') }}" class="{{ $navLinkClasses(request()->routeIs('settings.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('settings.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.078 2.25c-.917-.293-1.919.293-2.107 1.246l-.09.462a1.5 1.5 0 0 1-.9 1.107l-.45.184a1.5 1.5 0 0 1-1.408-.13l-.397-.264c-.816-.542-1.933-.334-2.454.478a7.51 7.51 0 0 0-.782 1.62c-.313.902.19 1.874 1.078 2.157l.445.142a1.5 1.5 0 0 1 1.02 1.4v.5a1.5 1.5 0 0 1-1.02 1.4l-.445.142c-.888.283-1.39 1.255-1.078 2.157.211.573.474 1.12.782 1.62.521.812 1.638 1.02 2.454.478l.397-.264a1.5 1.5 0 0 1 1.408-.13l.45.184a1.5 1.5 0 0 1 .9 1.107l.09.462c.188.953 1.19 1.539 2.107 1.246a7.55 7.55 0 0 0 1.844 0c.917.293 1.919-.293 2.107-1.246l.09-.462a1.5 1.5 0 0 1 .9-1.107l.45-.184a1.5 1.5 0 0 1 1.408.13l.397.264c.816.542 1.933.334 2.454-.478.308-.5.571-1.047.782-1.62.313-.902-.19-1.874-1.078-2.157l-.445-.142a1.5 1.5 0 0 1-1.02-1.4v-.5a1.5 1.5 0 0 1 1.02-1.4l.445-.142c.888-.283 1.39-1.255 1.078-2.157a7.508 7.508 0 0 0-.782-1.62c-.521-.812-1.638-1.02-2.454-.478l-.397.264a1.5 1.5 0 0 1-1.408.13l-.45-.184a1.5 1.5 0 0 1-.9-1.107l-.09-.462c-.188-.953-1.19-1.539-2.107-1.246a7.55 7.55 0 0 0-1.844 0ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Settings</span>
                </a>
            </li>
        @endif
        @if(auth()->user()->hasRole('guidance_counselor'))
            <li>
                <a href="{{ route('flagged-cases.index') }}" class="{{ $navLinkClasses(request()->routeIs('flagged-cases.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('flagged-cases.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a1 1 0 0 1 1 1v.586A2 2 0 0 1 12.414 3H15a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-3a1 1 0 0 1-.707-.293L10 14.414l-1.293 1.293A1 1 0 0 1 8 16H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2.586A2 2 0 0 1 9 2.586V2a1 1 0 0 1 1-1Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Flagged Students</span>
                </a>
            </li>
            <li>
                <a href="{{ route('counseling-sessions.index') }}" class="{{ $navLinkClasses(request()->routeIs('counseling-sessions.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('counseling-sessions.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a1 1 0 0 1 1 1v3.586l2.207 2.207a1 1 0 0 1-1.414 1.414l-2.5-2.5A1 1 0 0 1 9 10V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Counseling Sessions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('notifications.index') }}" class="{{ $navLinkClasses(request()->routeIs('notifications.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('notifications.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a6 6 0 0 0-6 6c0 3.5-1 5-1 5h14s-1-1.5-1-5a6 6 0 0 0-6-6Zm0 16a2 2 0 0 0 2-2H8a2 2 0 0 0 2 2Z" /></svg>
                    <span class="{{ $labelClass }}">Notifications</span>
                    @if($unreadNotificationsCount > 0)
                        <span class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-md bg-gold px-1.5 py-0.5 text-xs font-bold text-white">{{ $unreadNotificationsCount }}</span>
                    @endif
                </a>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="{{ $navLinkClasses(request()->routeIs('reports.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('reports.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7.414a1 1 0 0 0-.293-.707l-3.414-3.414A1 1 0 0 0 12.586 3H4Zm2 9a1 1 0 1 1 0-2h8a1 1 0 1 1 0 2H6Zm0-4a1 1 0 0 1 0-2h4a1 1 0 1 1 0 2H6Z" clip-rule="evenodd" /></svg>
                    <span class="{{ $labelClass }}">Reports</span>
                </a>
            </li>
        @endif
        <li>
            <a href="{{ route('profile.edit') }}" class="{{ $navLinkClasses(request()->routeIs('profile.*')) }}">
                <svg class="{{ $iconClasses(request()->routeIs('profile.*')) }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 10a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm0 2c-4 0-7 2-7 4.5V18h14v-1.5C17 14 14 12 10 12Z" /></svg>
                <span class="{{ $labelClass }}">Profile</span>
            </a>
        </li>
        <li class="mt-auto pt-6">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="group flex w-full items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-white/85 transition hover:bg-tint hover:text-primary">
                    <svg class="h-5 w-5 text-gold transition group-hover:text-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 4a1 1 0 0 0-1 1v2h2V6h6v8H8v-1H6v2a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H7Zm-.293 3.707L3.414 10H12v-1.5H3.414l3.293-2.707-1-1.086Z" /></svg>
                    <span class="{{ $labelClass }}">Logout</span>
                </button>
            </form>
        </li>
    </ul>
</nav>
