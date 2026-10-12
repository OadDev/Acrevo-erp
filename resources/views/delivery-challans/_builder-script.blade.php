<script>
    function challanBuilder(initial) {
        return {
            items: initial.items,
            catalog: initial.catalog,
            addItem() {
                this.items.push({ item_id: null, name: '', hsn_sac_code: '', unit: 'Nos', quantity: 1 });
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
            },
        };
    }
</script>
