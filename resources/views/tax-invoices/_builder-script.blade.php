<script>
    function billingBuilder(initial) {
        return {
            items: initial.items,
            taxPercent: initial.taxPercent,
            catalog: initial.catalog,
            addItem() {
                this.items.push({ item_id: null, name: '', hsn_sac_code: '', unit: 'Nos', quantity: 1, rate: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) this.items.splice(index, 1);
            },
            applyCatalogItem(item, catalogId) {
                const found = this.catalog.find(c => String(c.id) === String(catalogId));
                if (!found) { item.item_id = null; return; }
                item.item_id = found.id;
                item.name = found.name;
                item.hsn_sac_code = found.hsn_sac_code;
                item.unit = found.unit;
                item.rate = found.rate;
            },
            lineTotal(item) {
                return (parseFloat(item.quantity) || 0) * (parseFloat(item.rate) || 0);
            },
            get subtotal() {
                return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
            },
            get taxAmount() {
                return this.subtotal * ((parseFloat(this.taxPercent) || 0) / 100);
            },
            get grandTotal() {
                return this.subtotal + this.taxAmount;
            },
            money(value) {
                return (value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    }
</script>
