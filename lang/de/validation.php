<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Deutsch)
    |--------------------------------------------------------------------------
    |
    | Die folgenden Sprachzeilen enthalten die Standard-Fehlermeldungen der
    | Validierungsklasse. Einige Regeln haben mehrere Versionen (z. B. size).
    |
    */

    'accepted'             => ':attribute muss akzeptiert werden.',
    'accepted_if'          => ':attribute muss akzeptiert werden, wenn :other :value ist.',
    'active_url'           => ':attribute ist keine gültige URL.',
    'after'                => ':attribute muss ein Datum nach :date sein.',
    'after_or_equal'       => ':attribute muss ein Datum nach oder gleich :date sein.',
    'alpha'                => ':attribute darf nur Buchstaben enthalten.',
    'alpha_dash'           => ':attribute darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
    'alpha_num'            => ':attribute darf nur Buchstaben und Zahlen enthalten.',
    'array'                => ':attribute muss ein Array sein.',
    'ascii'                => ':attribute darf nur einstellige alphanumerische Zeichen und Symbole enthalten.',
    'before'               => ':attribute muss ein Datum vor :date sein.',
    'before_or_equal'      => ':attribute muss ein Datum vor oder gleich :date sein.',
    'between'              => [
        'array'   => ':attribute muss zwischen :min und :max Elemente haben.',
        'file'    => ':attribute muss zwischen :min und :max Kilobytes groß sein.',
        'numeric' => ':attribute muss zwischen :min und :max liegen.',
        'string'  => ':attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean'              => ':attribute muss wahr oder falsch sein.',
    'can'                  => ':attribute enthält einen nicht erlaubten Wert.',
    'confirmed'            => ':attribute stimmt nicht mit der Bestätigung überein.',
    'current_password'     => 'Das Passwort ist falsch.',
    'date'                 => ':attribute ist kein gültiges Datum.',
    'date_equals'          => ':attribute muss ein Datum gleich :date sein.',
    'date_format'          => ':attribute entspricht nicht dem Format :format.',
    'decimal'              => ':attribute muss :decimal Nachkommastellen haben.',
    'declined'             => ':attribute muss abgelehnt werden.',
    'declined_if'          => ':attribute muss abgelehnt werden, wenn :other :value ist.',
    'different'            => ':attribute und :other müssen sich unterscheiden.',
    'digits'               => ':attribute muss :digits Stellen haben.',
    'digits_between'       => ':attribute muss zwischen :min und :max Stellen haben.',
    'dimensions'           => ':attribute hat ungültige Bildabmessungen.',
    'distinct'             => ':attribute hat einen doppelten Wert.',
    'doesnt_end_with'      => ':attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with'    => ':attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email'                => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'ends_with'            => ':attribute muss mit einem der folgenden Werte enden: :values.',
    'enum'                 => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists'               => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions'           => ':attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file'                 => ':attribute muss eine Datei sein.',
    'filled'               => ':attribute muss einen Wert haben.',
    'gt'                   => [
        'array'   => ':attribute muss mehr als :value Elemente haben.',
        'file'    => ':attribute muss größer als :value Kilobytes sein.',
        'numeric' => ':attribute muss größer als :value sein.',
        'string'  => ':attribute muss mehr als :value Zeichen haben.',
    ],
    'gte'                  => [
        'array'   => ':attribute muss :value oder mehr Elemente haben.',
        'file'    => ':attribute muss größer oder gleich :value Kilobytes sein.',
        'numeric' => ':attribute muss größer oder gleich :value sein.',
        'string'  => ':attribute muss :value oder mehr Zeichen haben.',
    ],
    'hex_color'            => ':attribute muss eine gültige Hexadezimalfarbe sein.',
    'image'                => ':attribute muss ein Bild sein.',
    'in'                   => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array'             => ':attribute ist nicht in :other vorhanden.',
    'integer'              => ':attribute muss eine ganze Zahl sein.',
    'ip'                   => ':attribute muss eine gültige IP-Adresse sein.',
    'ipv4'                 => ':attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6'                 => ':attribute muss eine gültige IPv6-Adresse sein.',
    'json'                 => ':attribute muss ein gültiger JSON-String sein.',
    'list'                 => ':attribute muss eine Liste sein.',
    'lowercase'            => ':attribute muss in Kleinbuchstaben geschrieben sein.',
    'lt'                   => [
        'array'   => ':attribute muss weniger als :value Elemente haben.',
        'file'    => ':attribute muss kleiner als :value Kilobytes sein.',
        'numeric' => ':attribute muss kleiner als :value sein.',
        'string'  => ':attribute muss weniger als :value Zeichen haben.',
    ],
    'lte'                  => [
        'array'   => ':attribute darf höchstens :value Elemente haben.',
        'file'    => ':attribute muss kleiner oder gleich :value Kilobytes sein.',
        'numeric' => ':attribute muss kleiner oder gleich :value sein.',
        'string'  => ':attribute darf höchstens :value Zeichen haben.',
    ],
    'mac_address'          => ':attribute muss eine gültige MAC-Adresse sein.',
    'max'                  => [
        'array'   => ':attribute darf höchstens :max Elemente haben.',
        'file'    => ':attribute darf höchstens :max Kilobytes groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string'  => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'max_digits'           => ':attribute darf höchstens :max Stellen haben.',
    'mimes'                => ':attribute muss eine Datei vom Typ :values sein.',
    'mimetypes'            => ':attribute muss eine Datei vom Typ :values sein.',
    'min'                  => [
        'array'   => ':attribute muss mindestens :min Elemente haben.',
        'file'    => ':attribute muss mindestens :min Kilobytes groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string'  => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits'           => ':attribute muss mindestens :min Stellen haben.',
    'missing'              => ':attribute darf nicht vorhanden sein.',
    'missing_if'           => ':attribute darf nicht vorhanden sein, wenn :other :value ist.',
    'missing_unless'       => ':attribute darf nicht vorhanden sein, es sei denn, :other ist :value.',
    'missing_with'         => ':attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all'     => ':attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of'          => ':attribute muss ein Vielfaches von :value sein.',
    'not_in'               => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex'            => ':attribute hat ein ungültiges Format.',
    'numeric'              => ':attribute muss eine Zahl sein.',
    'password'             => [
        'letters'       => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed'         => ':attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers'       => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols'       => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute ist in einem Datenleck aufgetaucht. Bitte wählen Sie ein anderes :attribute.',
    ],
    'present'              => ':attribute muss vorhanden sein.',
    'present_if'           => ':attribute muss vorhanden sein, wenn :other :value ist.',
    'present_unless'       => ':attribute muss vorhanden sein, es sei denn, :other ist :value.',
    'present_with'         => ':attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all'     => ':attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited'           => ':attribute ist nicht erlaubt.',
    'prohibited_if'        => ':attribute ist nicht erlaubt, wenn :other :value ist.',
    'prohibited_unless'    => ':attribute ist nicht erlaubt, es sei denn, :other ist in :values.',
    'prohibits'            => ':attribute verbietet die Anwesenheit von :other.',
    'regex'                => ':attribute hat ein ungültiges Format.',
    'required'             => ':attribute ist erforderlich.',
    'required_array_keys'  => ':attribute muss Einträge für folgende Schlüssel enthalten: :values.',
    'required_if'          => ':attribute ist erforderlich, wenn :other :value ist.',
    'required_if_accepted' => ':attribute ist erforderlich, wenn :other akzeptiert wird.',
    'required_unless'      => ':attribute ist erforderlich, es sei denn, :other ist in :values.',
    'required_with'        => ':attribute ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all'    => ':attribute ist erforderlich, wenn :values vorhanden sind.',
    'required_without'     => ':attribute ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => ':attribute ist erforderlich, wenn keine der folgenden vorhanden sind: :values.',
    'same'                 => ':attribute und :other müssen übereinstimmen.',
    'size'                 => [
        'array'   => ':attribute muss :size Elemente enthalten.',
        'file'    => ':attribute muss :size Kilobytes groß sein.',
        'numeric' => ':attribute muss :size sein.',
        'string'  => ':attribute muss :size Zeichen lang sein.',
    ],
    'starts_with'          => ':attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string'               => ':attribute muss eine Zeichenkette sein.',
    'timezone'             => ':attribute muss eine gültige Zeitzone sein.',
    'unique'               => ':attribute ist bereits vergeben.',
    'uploaded'             => ':attribute konnte nicht hochgeladen werden.',
    'uppercase'            => ':attribute muss in Großbuchstaben geschrieben sein.',
    'url'                  => ':attribute muss eine gültige URL sein.',
    'ulid'                 => ':attribute muss eine gültige ULID sein.',
    'uuid'                 => ':attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Hier können benutzerdefinierte Validierungsmeldungen für Attribute
    | angegeben werden, indem die Konvention "attribute.rule" verwendet wird.
    |
    */

    'custom' => [
        'email' => [
            'unique' => 'Diese E-Mail-Adresse ist bereits registriert.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Die folgenden Sprachzeilen werden verwendet, um Attribut-Platzhalter
    | durch besser lesbare Bezeichnungen zu ersetzen.
    |
    */

    'attributes' => [
        'amount_euros'       => 'Betrag',
        'artikelnummer'      => 'Artikelnummer',
        'produktname'        => 'Produktname',
        'email'              => 'E-Mail-Adresse',
        'name'               => 'Name',
        'first_name'         => 'Vorname',
        'last_name'          => 'Nachname',
        'customer_number'    => 'Kundennummer',
        'customer_group_id'  => 'Kundengruppe',
        'price_display_mode' => 'Preisanzeige',
        'base_price_net_eur' => 'Netto-Preis',
        'availability_mode'  => 'Verfügbarkeit',
        'tax_rate_id'        => 'Steuersatz',
        'currency'           => 'Währung',
        'quantity'           => 'Menge',
        'type'               => 'Typ',
        'note'               => 'Notiz',
    ],

];
