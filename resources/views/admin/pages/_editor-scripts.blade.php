{{--
    Quill 2.x Editor — Bild (URL) + Video (YouTube/Vimeo) + volle Toolbar
    Wird in create.blade.php und edit.blade.php via @include eingebunden.
    Erwartet: $content (Blade-String mit initialem HTML-Inhalt)
--}}
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    // ── Initial content aus PHP-Variable ──────────────────────────────────
    const initialContent = @json($content ?? '');

    // ── Quill initialisieren ──────────────────────────────────────────────
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ indent: '-1' }, { indent: '+1' }],
                    [{ align: [] }],
                    ['link', 'image', 'video'],
                    ['blockquote', 'code-block'],
                    ['clean'],
                ],
                handlers: {
                    image: insertImageByUrl,
                    video: insertVideoByUrl,
                },
            },
        },
    });

    // Initialinhalt setzen
    if (initialContent) {
        quill.clipboard.dangerouslyPasteHTML(initialContent);
    }

    // ── Vor dem Absenden: HTML ins Textarea ───────────────────────────────
    document.getElementById('page-form').addEventListener('submit', function () {
        document.getElementById('content-input').value = quill.getSemanticHTML();
    });

    // ── Bild per URL einfügen ─────────────────────────────────────────────
    function insertImageByUrl() {
        openDialog(
            '🖼 Bild einfügen',
            'Bild-URL (https://…)',
            'Einfügen',
            function (url) {
                if (!url) return;
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'image', url, 'user');
                quill.setSelection(range.index + 1);
            }
        );
    }

    // ── Video per URL einfügen (YouTube, Vimeo, direkte MP4-URL) ─────────
    function insertVideoByUrl() {
        openDialog(
            '▶ Video einfügen',
            'Video-URL (YouTube, Vimeo oder direkte MP4-URL)',
            'Einfügen',
            function (url) {
                if (!url) return;
                const range = quill.getSelection(true);
                quill.insertEmbed(range.index, 'video', url, 'user');
                quill.setSelection(range.index + 1);
            }
        );
    }

    // ── Modaler Dialog (URL-Eingabe) ──────────────────────────────────────
    function openDialog(title, placeholder, confirmLabel, onConfirm) {
        const overlay = document.createElement('div');
        overlay.className = 'ql-insert-dialog';
        overlay.innerHTML = `
            <div class="ql-insert-dialog-box">
                <h3>${title}</h3>
                <input type="text" id="ql-url-input" placeholder="${placeholder}" autofocus>
                <div class="dialog-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="ql-cancel">Abbrechen</button>
                    <button type="button" class="btn btn-primary btn-sm" id="ql-confirm">${confirmLabel}</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        const input = overlay.querySelector('#ql-url-input');
        input.focus();

        function close() { document.body.removeChild(overlay); }

        overlay.querySelector('#ql-cancel').addEventListener('click', close);
        overlay.querySelector('#ql-confirm').addEventListener('click', function () {
            onConfirm(input.value.trim());
            close();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { onConfirm(input.value.trim()); close(); }
            if (e.key === 'Escape') { close(); }
        });
        // Klick auf Overlay schließt Dialog
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
    }

    // ── Slug aus Titel auto-generieren (nur auf Create-Seite) ────────────
    const titleInput = document.getElementById('page-title');
    const slugInput  = document.getElementById('page-slug');
    if (titleInput && slugInput && !slugInput.readOnly) {
        let slugManuallyEdited = slugInput.value.length > 0;
        titleInput.addEventListener('input', function () {
            if (slugManuallyEdited) return;
            slugInput.value = titleInput.value
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Umlaute abbauen
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        });
        slugInput.addEventListener('input', function () {
            slugManuallyEdited = slugInput.value.length > 0;
        });
    }
}());
</script>
