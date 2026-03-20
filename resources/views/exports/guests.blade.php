<!DOCTYPE html>
<html lang="sk">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; padding: 0 32px; }

    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; }
    .header h1 { font-size: 18px; font-weight: bold; color: #1e3a5f; }
    .header p { font-size: 9px; color: #6b7280; margin-top: 3px; }

    .section-title { font-size: 12px; font-weight: bold; color: #1e3a5f; margin: 16px 0 6px 0; padding: 4px 8px; background: #e8eef6; border-left: 3px solid #1e3a5f; }
    .teacher-section-title { border-left-color: #7c3aed; background: #f3eeff; color: #4c1d95; }

    table { width: 100%; border-collapse: collapse; }
    th { background: #1e3a5f; color: #fff; padding: 5px 7px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; }
    .teacher-th { background: #7c3aed; }
    td { padding: 4px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #f9fafb; }
    .allergens { color: #dc2626; font-style: italic; }
    .no-data { text-align: center; color: #9ca3af; padding: 12px; font-style: italic; }

    .footer { margin-top: 20px; text-align: right; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
</style>
</head>
<body>

<div class="header">
    <h1>Zoznam hostí</h1>
    <p>Vygenerované: {{ $generatedAt }}</p>
</div>

{{-- Main list --}}
@if($teacherList->isNotEmpty())
    <div class="section-title">Hostia ({{ $mainList->count() }})</div>
@endif

@if($mainList->isNotEmpty())
<table>
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th>Meno</th>
            @if($includeSeat)
                <th style="width:10%">Stôl</th>
                <th style="width:10%">Miesto</th>
            @endif
            @if($includeAllergens)
                <th style="width:25%">Alergény</th>
            @endif
            @if($includeTicket)
                <th style="width:10%">Č. lístka</th>
            @endif
            <th style="width:15%">Rezervácia</th>
        </tr>
    </thead>
    <tbody>
        @foreach($mainList as $i => $guest)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $guest->name }}</td>
            @if($includeSeat)
                <td>{{ $guest->table->name ?? '-' }}</td>
                <td>{{ $guest->seat_number ?? '-' }}</td>
            @endif
            @if($includeAllergens)
                <td class="{{ $guest->allergens_display ? 'allergens' : '' }}">{{ $guest->allergens_display ?: '-' }}</td>
            @endif
            @if($includeTicket)
                <td style="font-weight:bold;font-family:monospace">{{ $guest->ticket_code ?: '-' }}</td>
            @endif
            <td>{{ $guest->registration->reservation_number ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
    <p class="no-data">Žiadni hostia</p>
@endif

{{-- Teachers list --}}
@if($teacherList->isNotEmpty())
    <div class="section-title teacher-section-title">Učitelia ({{ $teacherList->count() }})</div>
    <table>
        <thead>
            <tr class="teacher-th">
                <th class="teacher-th" style="width:5%">#</th>
                <th class="teacher-th">Meno</th>
                @if($includeSeat)
                    <th class="teacher-th" style="width:10%">Stôl</th>
                    <th class="teacher-th" style="width:10%">Miesto</th>
                @endif
                @if($includeAllergens)
                    <th class="teacher-th" style="width:25%">Alergény</th>
                @endif
                @if($includeTicket)
                    <th class="teacher-th" style="width:10%">Č. lístka</th>
                @endif
                <th class="teacher-th" style="width:15%">Rezervácia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($teacherList as $i => $guest)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $guest->name }}</td>
                @if($includeSeat)
                    <td>{{ $guest->table->name ?? '-' }}</td>
                    <td>{{ $guest->seat_number ?? '-' }}</td>
                @endif
                @if($includeAllergens)
                    <td class="{{ $guest->allergens_display ? 'allergens' : '' }}">{{ $guest->allergens_display ?: '-' }}</td>
                @endif
                @if($includeTicket)
                    <td style="font-weight:bold;font-family:monospace">{{ $guest->ticket_code ?: '-' }}</td>
                @endif
                <td>{{ $guest->registration->reservation_number ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Celkový počet hostí: {{ $mainList->count() + $teacherList->count() }}</div>

</body>
</html>
