<script>
    function quotationBuilder(initial) {
        return {
            items: initial.items,
            taxPercent: initial.taxPercent,
            discountType: initial.discountType,
            discountValue: initial.discountValue,
            addItem() {
                this.items.push({ item_type: 'service', name: '', description: '', unit: 'Nos', quantity: 1, unit_price: 0, discount: 0 });
            },
            removeItem(index) {
                if (this.items.length > 1) this.items.splice(index, 1);
            },
            lineTotal(item) {
                // Pre-tax line total - matches subtotal below and what the
                // server stores per item. Tax is applied once, at the
                // quotation level, in the Pricing Summary.
                return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0) - (parseFloat(item.discount) || 0);
            },
            get subtotal() {
                return this.items.reduce((sum, item) => sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0) - (parseFloat(item.discount) || 0)), 0);
            },
            get discountAmount() {
                return this.discountType === 'percent' ? this.subtotal * ((parseFloat(this.discountValue) || 0) / 100) : (parseFloat(this.discountValue) || 0);
            },
            get taxable() {
                return Math.max(this.subtotal - this.discountAmount, 0);
            },
            get taxAmount() {
                return this.taxable * ((parseFloat(this.taxPercent) || 0) / 100);
            },
            get grandTotal() {
                return this.taxable + this.taxAmount;
            },
            money(value) {
                return '₹' + (value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
            },
        };
    }
</script>
