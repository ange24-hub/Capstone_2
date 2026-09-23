@props(['status' => 'neutral'])

<span {{ $attributes->class(['badge', $status, 'inline-flex items-center gap-1.5 border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700']) }}>{{ $slot }}</span>
