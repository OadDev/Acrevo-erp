import './bootstrap';

import Alpine from 'alpinejs';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Store alignment as an inline style rather than Quill's default
// "ql-align-*" class, so a saved paragraph's alignment still renders
// correctly wherever it's displayed later (show pages, PDFs) without
// needing Quill's own editor CSS loaded there too.
Quill.register(Quill.import('attributors/style/align'), true);

window.Alpine = Alpine;

Alpine.data('richTextEditor', (initial = '') => ({
    quill: null,

    init() {
        this.quill = new Quill(this.$refs.editor, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['clean'],
                ],
            },
        });

        if (initial) {
            this.quill.clipboard.dangerouslyPasteHTML(initial);
        }

        this.$refs.input.value = this.quill.root.innerHTML;
        this.quill.on('text-change', () => {
            this.$refs.input.value = this.quill.getText().trim() === '' ? '' : this.quill.root.innerHTML;
        });
    },
}));

Alpine.start();
