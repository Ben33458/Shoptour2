@php
    use App\Services\CustomerActivationService;

    $step        = request()->query('onboarding_step');
    $showHelp    = request()->query('show_help') === '1';
    $steps       = CustomerActivationService::tourSteps();
    $stepKeys    = array_column($steps, 'key');
    $stepIndex   = array_search($step, $stepKeys, true);

    // Only render when actively in an onboarding step
    if ($step === null || $stepIndex === false) return;

    $stepDef      = $steps[$stepIndex];
    $isLast       = ($stepIndex === count($steps) - 1);
    $nextUrl      = CustomerActivationService::nextStepUrl($step);
    $totalSteps   = count($steps);
    $currentNum   = $stepIndex + 1;

    // Determine helpbox state (from customer's display_preferences)
    $dismissed = [];
    if (isset($customer)) {
        $dismissed = ($customer->display_preferences['onboarding_helpbox_dismissed'] ?? []);
    }
    $isDismissed = in_array($step, $dismissed, true) && ! $showHelp;

    // Note: onboarding auto-completion is handled in AccountController::invoices()
    // to ensure it runs before any potential 404/403 aborts on that page.

    // Persist the current step so the resume hint in the layout can link back here
    if (isset($customer)) {
        $__prefs = $customer->display_preferences ?? [];
        if (($__prefs['onboarding_current_step'] ?? null) !== $step) {
            $__prefs['onboarding_current_step'] = $step;
            $customer->update(['display_preferences' => $__prefs]);
        }
    }
@endphp

{{-- Onboarding progress bar --}}
<div class="bg-amber-500 px-4 py-3">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 text-sm text-white font-medium flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            Konto-Einrichtung · Schritt {{ $currentNum }} von {{ $totalSteps }}
        </div>

        {{-- Step pills + Weiter-Button --}}
        <div class="flex items-center gap-2">
            <div class="flex gap-1">
                @foreach($steps as $i => $s)
                    @php
                        $sUrl      = route($s['route'], $s['params']);
                        $isDone    = $i < $stepIndex;
                        $isCurrent = $i === $stepIndex;
                    @endphp
                    @if($isDone)
                        <a href="{{ $sUrl }}"
                           class="flex-shrink-0 w-6 h-6 rounded-full bg-white text-amber-700 flex items-center justify-center text-xs"
                           title="{{ $s['label'] }}">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </a>
                    @elseif($isCurrent)
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-white text-amber-700 flex items-center justify-center text-xs font-bold">
                            {{ $currentNum }}
                        </span>
                    @else
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-amber-400 text-white flex items-center justify-center text-xs"
                              title="{{ $s['label'] }}">
                            {{ $i + 1 }}
                        </span>
                    @endif
                @endforeach
            </div>

            @if($isLast)
                <a href="{{ route('shop.index') }}"
                   class="ml-2 bg-white text-amber-700 font-semibold text-sm px-4 py-1.5 rounded-lg hover:bg-amber-50 transition-colors whitespace-nowrap">
                    Einrichtung abschließen ✓
                </a>
            @else
                <a href="{{ $nextUrl }}"
                   class="ml-2 bg-white text-amber-700 font-semibold text-sm px-4 py-1.5 rounded-lg hover:bg-amber-50 transition-colors whitespace-nowrap">
                    Weiter →
                </a>
            @endif
        </div>
    </div>
</div>

{{-- Helpbox --}}
@if(! $isDismissed)
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 mb-6 relative" id="onboarding-helpbox">
        <button type="button"
                onclick="document.getElementById('onboarding-helpbox').style.display='none'; document.getElementById('onboarding-help-link').style.display='block';"
                class="absolute top-3 right-3 text-blue-400 hover:text-blue-600 text-lg leading-none"
                title="Schließen">&times;</button>
        <form method="POST" action="{{ route('onboarding.helpbox.dismiss', $step) }}"
              id="helpbox-dismiss-form" class="hidden">
            @csrf
        </form>

        <p class="text-sm font-semibold text-blue-800 mb-1">{{ $stepDef['label'] }}</p>
        <p class="text-sm text-blue-700">{{ $stepDef['description'] }}</p>

        <button type="button"
                onclick="document.getElementById('onboarding-helpbox').style.display='none';
                         document.getElementById('onboarding-help-link').style.display='block';
                         document.getElementById('helpbox-dismiss-form').submit();"
                class="mt-3 text-xs text-blue-500 hover:text-blue-700 underline">
            Erklärung ausblenden
        </button>
    </div>
@endif

<div id="onboarding-help-link" style="{{ $isDismissed ? '' : 'display:none;' }}" class="mb-4">
    <a href="{{ request()->fullUrlWithQuery(['onboarding_step' => $step, 'show_help' => '1']) }}"
       class="text-xs text-amber-600 hover:underline inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0z"/>
        </svg>
        Was kann ich hier machen?
    </a>
</div>

