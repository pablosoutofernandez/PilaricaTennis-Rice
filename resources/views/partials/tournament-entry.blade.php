{{-- Entrada al torneo para el público: la primera vez, eliges quién eres y la ruleta descubre a tu compañero. --}}
@php $players = $tournament->playersWithPartners(); @endphp
@if ($players)
    <div x-data="tournamentEntry(@js(['key' => "torneo-entrada-{$tournament->id}", 'players' => $players]))"
         x-show="open" x-cloak x-transition.opacity.duration.300ms
         role="dialog" aria-modal="true" aria-labelledby="entrada-titulo"
         class="fixed inset-0 z-50 overflow-y-auto overflow-x-hidden bg-gradient-to-b from-brand-500 via-brand-400 to-ball-300 text-white">
        <div class="relative flex min-h-full flex-col items-center justify-center px-4 py-10 text-center">

            <section x-show="step === 'welcome'" class="flex flex-col items-center">
                <span class="grid size-24 animate-bounce place-items-center rounded-full bg-ball-300 text-5xl shadow-xl ring-4 ring-white/70 md:size-28 md:text-6xl">🎾</span>
                <p class="mt-8 text-sm font-semibold tracking-widest text-white/80 uppercase">
                    {{ ucfirst($tournament->date->translatedFormat('l j \d\e F')) }}
                </p>
                <h1 id="entrada-titulo" class="mt-1 max-w-2xl text-4xl font-black break-words drop-shadow-sm md:text-6xl">{{ $tournament->name }}</h1>
                <button type="button" x-on:click="step = 'who'"
                        class="btn mt-10 min-h-14 rounded-2xl bg-white px-10 text-lg text-brand-700 shadow-xl hover:bg-brand-50">
                    Entrar al torneo
                </button>
                <button type="button" x-on:click="close()" class="mt-5 min-h-11 px-4 text-sm font-semibold text-white/85 underline-offset-4 hover:underline">
                    Solo vengo a mirar
                </button>
            </section>

            <section x-show="step === 'who'" x-cloak class="w-full max-w-sm">
                <h2 class="text-3xl font-black md:text-4xl">¿Quién eres?</h2>
                <form x-on:submit.prevent="discover()" class="mt-6 rounded-3xl bg-white p-5 text-left text-stone-800 shadow-xl">
                    <label for="entrada-jugador" class="label">Tu nombre</label>
                    <select id="entrada-jugador" x-model="chosen" class="input min-h-12 text-base">
                        <option value="">Elige tu nombre…</option>
                        @foreach ($players as $player)
                            <option value="{{ $player['name'] }}">{{ $player['name'] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" x-bind:disabled="! chosen" class="btn-primary mt-4 min-h-12 w-full text-base">
                        Descubrir mi pareja
                    </button>
                </form>
                <button type="button" x-on:click="close()" class="mt-5 min-h-11 px-4 text-sm font-semibold text-white/85 underline-offset-4 hover:underline">
                    Solo vengo a mirar
                </button>
            </section>

            <section x-show="step === 'spin' || step === 'reveal'" x-cloak class="flex w-full flex-col items-center">
                <p class="text-sm font-semibold tracking-widest text-white/80 uppercase" x-text="player?.name"></p>
                <h2 class="mt-1 text-3xl font-black md:text-4xl" x-text="step === 'reveal' ? '¡Te ha tocado!' : '¿Con quién juegas?'"></h2>

                <div x-show="step === 'spin'" class="relative mt-8 aspect-square w-[min(84vw,24rem)]">
                    {{-- Aguja fija arriba: los topes de cada casilla la empujan al pasar --}}
                    <svg x-ref="needle" viewBox="0 0 40 70" aria-hidden="true"
                         class="absolute -top-6 left-1/2 z-10 w-10 -translate-x-1/2 drop-shadow-lg" style="transform-origin: 20px 16px">
                        <path d="M14 16 L20 68 L26 16 Z" fill="#292524" />
                        <circle cx="20" cy="16" r="12" fill="#fff" stroke="var(--color-brand-500)" stroke-width="4" />
                        <circle cx="20" cy="16" r="4" fill="var(--color-brand-500)" />
                    </svg>
                    <div x-ref="wheel" class="absolute inset-0 overflow-hidden rounded-full shadow-2xl ring-8 ring-white" x-bind:style="wheelStyle()">
                        <template x-for="(name, index) in slices" x-bind:key="index">
                            <div class="absolute top-1/2 left-1/2 h-0 w-1/2 origin-left" x-bind:style="sliceStyle(index)">
                                <span class="absolute right-4 block max-w-[72%] -translate-y-1/2 truncate text-right text-sm font-bold md:text-base"
                                      x-bind:class="sliceLight(index) ? 'text-stone-800' : 'text-white'" x-text="name"></span>
                            </div>
                        </template>
                        <template x-for="(name, index) in slices" x-bind:key="'tope-' + index">
                            <div class="absolute top-1/2 left-1/2 h-0 w-1/2 origin-left" x-bind:style="pinStyle(index)">
                                <span class="absolute right-1 block size-2.5 -translate-y-1/2 rounded-full bg-white shadow"></span>
                            </div>
                        </template>
                    </div>
                    <div class="absolute top-1/2 left-1/2 grid size-14 -translate-1/2 place-items-center rounded-full bg-white text-2xl shadow-lg">🎾</div>
                </div>

                <div x-show="step === 'reveal'" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="scale-75 opacity-0"
                     class="mt-8 w-full max-w-md rounded-3xl bg-white px-6 py-8 text-stone-800 shadow-2xl" aria-live="polite">
                    <p class="text-sm font-semibold tracking-wide text-stone-500 uppercase">Tu pareja</p>
                    <p class="mt-2 text-4xl font-black break-words text-brand-700 md:text-5xl" x-text="player?.partner"></p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2" x-show="player?.number || player?.group">
                        <span x-show="player?.number" class="badge bg-ball-300 px-3 py-1 text-sm text-stone-800" x-text="'Pareja ' + player?.number"></span>
                        <span x-show="player?.group" class="badge bg-brand-100 px-3 py-1 text-sm text-brand-800" x-text="'Grupo ' + player?.group"></span>
                    </div>
                    <button type="button" x-on:click="close()" class="btn-primary mt-8 min-h-12 w-full text-base">Ver el torneo</button>
                </div>
            </section>

            <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
                <template x-for="(piece, index) in confetti" x-bind:key="index">
                    <span class="confetti" x-bind:style="piece.style"></span>
                </template>
            </div>
        </div>
    </div>
@endif
