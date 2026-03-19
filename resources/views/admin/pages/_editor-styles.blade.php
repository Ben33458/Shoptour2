{{-- Quill 2.x Styles + Admin-Overrides --}}
<style>
    .ql-container { font-size: 15px; }
    .ql-editor { min-height: 420px; line-height: 1.6; }
    #editor-wrapper {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        overflow: visible;
    }

    /* Quill toolbar: override admin.css global select/input rules */
    .ql-toolbar select,
    .ql-toolbar.ql-snow select {
        width: auto; padding: 0; border: none; background: transparent;
        font-size: inherit; color: inherit; box-shadow: none; transition: none;
    }
    .ql-toolbar select:focus,
    .ql-toolbar.ql-snow select:focus {
        outline: none; border: none; box-shadow: none;
    }

    /* Custom image/video insert dialog */
    .ql-insert-dialog {
        position: fixed; inset: 0; z-index: 1000;
        background: rgba(0,0,0,.45);
        display: flex; align-items: center; justify-content: center;
    }
    .ql-insert-dialog-box {
        background: #fff; border-radius: 8px; padding: 24px;
        min-width: 380px; max-width: 90vw; box-shadow: 0 8px 32px rgba(0,0,0,.2);
    }
    .ql-insert-dialog-box h3 { margin: 0 0 14px; font-size: 1rem; }
    .ql-insert-dialog-box input {
        width: 100%; padding: 8px 10px; border: 1px solid #d1d5db;
        border-radius: 4px; font-size: .9rem; margin-bottom: 12px;
    }
    .ql-insert-dialog-box .dialog-actions { display: flex; gap: 8px; justify-content: flex-end; }
</style>
