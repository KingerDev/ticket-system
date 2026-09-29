{{-- Jediný spôsob platby – rovnaký text vo všetkých e-mailoch. --}}
<p>
    Platí sa <strong>len v hotovosti, osobne u organizátorov</strong>.
    @if(config('ples.payment_info'))
        Nájdete nás: <strong>{{ config('ples.payment_info') }}</strong>.
    @else
        Termíny a miesto, kde nás nájdete, včas zverejníme.
    @endif
</p>
