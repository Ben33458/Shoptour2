{{--
  WP-20: Reusable Ansprechpartner / Contacts widget.

  Usage:
    @include('admin._partials.contacts_widget', ['contacts' => $entity->contacts])

  Submits as contacts[0][id], contacts[0][name], contacts[0][phone], ...
  The controller syncs these via Contact model.
--}}
<div class="card" style="margin-top:1.5rem">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <h3 style="margin:0;font-size:1rem">Ansprechpartner</h3>
        <button type="button" id="add-contact-btn" class="btn btn-sm">+ Hinzufügen</button>
    </div>

    <div id="contacts-container">
        @foreach(($contacts ?? collect()) as $i => $contact)
        <div class="contact-row" data-index="{{ $i }}" style="display:grid;grid-template-columns:2fr 1fr 2fr 1.5fr auto;gap:.5rem;margin-bottom:.5rem;align-items:center">
            <input type="hidden" name="contacts[{{ $i }}][id]" value="{{ $contact->id }}">
            <input type="hidden" name="contacts[{{ $i }}][sort_order]" value="{{ $i }}">
            <input type="text"   name="contacts[{{ $i }}][name]"  value="{{ old("contacts.{$i}.name",  $contact->name)  }}" placeholder="Name *" required style="width:100%">
            <input type="tel"    name="contacts[{{ $i }}][phone]" value="{{ old("contacts.{$i}.phone", $contact->phone) }}" placeholder="Telefon"  style="width:100%">
            <input type="email"  name="contacts[{{ $i }}][email]" value="{{ old("contacts.{$i}.email", $contact->email) }}" placeholder="E-Mail"   style="width:100%">
            <input type="text"   name="contacts[{{ $i }}][role]"  value="{{ old("contacts.{$i}.role",  $contact->role)  }}" placeholder="Funktion" style="width:100%">
            <button type="button" class="btn btn-danger btn-sm remove-contact-btn" title="Entfernen">✕</button>
        </div>
        @endforeach
    </div>

    <p id="contacts-empty-hint" style="color:var(--c-muted);font-size:.85rem;margin:0{{ ($contacts ?? collect())->isNotEmpty() ? ';display:none' : '' }}">
        Noch kein Ansprechpartner hinterlegt.
    </p>
</div>

<script>
(function () {
    'use strict';

    let nextIndex = {{ ($contacts ?? collect())->count() }};

    const container  = document.getElementById('contacts-container');
    const emptyHint  = document.getElementById('contacts-empty-hint');
    const addBtn     = document.getElementById('add-contact-btn');

    function updateEmptyHint() {
        emptyHint.style.display = container.querySelectorAll('.contact-row').length === 0 ? '' : 'none';
    }

    function attachRemove(row) {
        row.querySelector('.remove-contact-btn').addEventListener('click', function () {
            row.remove();
            updateEmptyHint();
        });
    }

    // Attach to pre-rendered rows
    container.querySelectorAll('.contact-row').forEach(attachRemove);

    addBtn.addEventListener('click', function () {
        const idx = nextIndex++;
        const row = document.createElement('div');
        row.className = 'contact-row';
        row.dataset.index = idx;
        row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 2fr 1.5fr auto;gap:.5rem;margin-bottom:.5rem;align-items:center';
        row.innerHTML = `
            <input type="hidden" name="contacts[${idx}][sort_order]" value="${idx}">
            <input type="text"   name="contacts[${idx}][name]"  placeholder="Name *" required style="width:100%">
            <input type="tel"    name="contacts[${idx}][phone]" placeholder="Telefon"         style="width:100%">
            <input type="email"  name="contacts[${idx}][email]" placeholder="E-Mail"          style="width:100%">
            <input type="text"   name="contacts[${idx}][role]"  placeholder="Funktion"        style="width:100%">
            <button type="button" class="btn btn-danger btn-sm remove-contact-btn" title="Entfernen">✕</button>
        `;
        container.appendChild(row);
        attachRemove(row);
        updateEmptyHint();
        row.querySelector('input[type=text]').focus();
    });

    updateEmptyHint();
})();
</script>
