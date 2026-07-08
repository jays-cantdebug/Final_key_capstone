@props(['colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center text-sm text-slate-500">
        {{ $slot }}
    </td>
</tr>
