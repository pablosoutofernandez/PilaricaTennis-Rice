@props(['pair'])

@if ($pair?->number)
    <span {{ $attributes->class('ml-1 inline-grid min-w-5 place-items-center rounded-md bg-stone-100 px-1 align-[1px] text-[10px] leading-4 font-bold text-stone-500 tabular-nums') }}
          title="Pareja {{ $pair->number }}">{{ $pair->number }}</span>
@endif
