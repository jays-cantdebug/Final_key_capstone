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
                <svg class="{{ $iconClasses(request()->routeIs('dashboard') || request()->routeIs('*.dashboard')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                <span class="{{ $labelClass }}">Dashboard</span>
            </a>
        </li>
        @if(auth()->user()->hasRole('psychometrician'))
            <li>
                <a href="{{ route('assessments.create') }}" class="{{ $navLinkClasses(request()->routeIs('assessments.create*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('assessments.create*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    <span class="{{ $labelClass }}">New Assessment</span>
                </a>
            </li>
            <li>
                <a href="{{ route('students.index') }}" class="{{ $navLinkClasses(request()->routeIs('students.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('students.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                    <span class="{{ $labelClass }}">Students</span>
                </a>
            </li>
            <li>
                <a href="{{ route('questionnaires.index') }}" class="{{ $navLinkClasses(request()->routeIs('questionnaires.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('questionnaires.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                    <span class="{{ $labelClass }}">Questions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="{{ $navLinkClasses(request()->routeIs('reports.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('reports.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                    <span class="{{ $labelClass }}">Reports</span>
                </a>
            </li>
            <li>
                <a href="{{ route('audit-logs.index') }}" class="{{ $navLinkClasses(request()->routeIs('audit-logs.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('audit-logs.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                    <span class="{{ $labelClass }}">Audit Logs</span>
                </a>
            </li>
        @endif
        @if(auth()->user()->hasRole('guidance_counselor'))
            <li>
                <a href="{{ route('flagged-cases.index') }}" class="{{ $navLinkClasses(request()->routeIs('flagged-cases.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('flagged-cases.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>
                    <span class="{{ $labelClass }}">Flagged Students</span>
                </a>
            </li>
            <li>
                <a href="{{ route('counseling-sessions.index') }}" class="{{ $navLinkClasses(request()->routeIs('counseling-sessions.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('counseling-sessions.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <span class="{{ $labelClass }}">Counseling Sessions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('notifications.index') }}" class="{{ $navLinkClasses(request()->routeIs('notifications.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('notifications.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                    <span class="{{ $labelClass }}">Notifications</span>
                    @if($unreadNotificationsCount > 0)
                        <span class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-md bg-gold px-1.5 py-0.5 text-xs font-bold text-white">{{ $unreadNotificationsCount }}</span>
                    @endif
                </a>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="{{ $navLinkClasses(request()->routeIs('reports.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('reports.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                    <span class="{{ $labelClass }}">Reports</span>
                </a>
            </li>
        @endif
        <li>
            <a href="{{ route('profile.edit') }}" class="{{ $navLinkClasses(request()->routeIs('profile.*')) }}">
                <svg class="{{ $iconClasses(request()->routeIs('profile.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                <span class="{{ $labelClass }}">Profile</span>
            </a>
        </li>
        @if(auth()->user()->hasRole('psychometrician'))
            <li>
                <a href="{{ route('settings.edit') }}" class="{{ $navLinkClasses(request()->routeIs('settings.*')) }}">
                    <svg class="{{ $iconClasses(request()->routeIs('settings.*')) }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <span class="{{ $labelClass }}">Settings</span>
                </a>
            </li>
        @endif
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
