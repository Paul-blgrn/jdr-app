<h1>{{ $board->name }}</h1>
<p>
    {{
        $board->description . " " . $board->capacity . " " . $board->invitation_code
    }}
</p>
