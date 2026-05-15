/**
 * employees.js — Alpine.js component for the Employees index page.
 *
 * Registered globally as window.employeesData so Blade can use:
 *   x-data="employeesData(@json(url('employees')))"
 */
export function employeesData(baseUpdateUrl) {
    return {
        openCreate: false,
        openEdit:   false,
        editingEmployee: {},
        baseUpdateUrl,

        async fetchRates(id) {
            try {
                const res = await fetch(`${this.baseUpdateUrl}/${id}/rates`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const json = await res.json();
                const all  = json.data || [];

                this.editingEmployee        = this.editingEmployee || {};
                this.editingEmployee.rates  = all;

                const types = ['daily_rate', 'hourly_rate', 'hours_per_day'];
                this.editingEmployee.ratesByType  = {};
                this.editingEmployee.rateVisible  = this.editingEmployee.rateVisible || {};

                types.forEach(t => {
                    let list = all
                        .filter(r => r.rate_type === t)
                        .sort((a, b) =>
                            new Date(b.effective_from || b.created_at) -
                            new Date(a.effective_from || a.created_at),
                        );
                    list = list.map((r, i) =>
                        Object.assign({}, r, {
                            prev_amount: list[i + 1] ? list[i + 1].amount : null,
                        }),
                    );
                    this.editingEmployee.ratesByType[t] = list;
                    if (!this.editingEmployee.rateVisible[t]) {
                        this.editingEmployee.rateVisible[t] = 5;
                    }
                });

                // Overwrite the denormalized rate fields with the latest value from history
                // so the modal input always shows the true current rate, not a stale column value.
                const latestOf = (type) => {
                    const list = this.editingEmployee.ratesByType[type];
                    return list && list.length ? parseFloat(list[0].amount) : null;
                };
                const latestDaily  = latestOf('daily_rate');
                const latestHourly = latestOf('hourly_rate');
                const latestHpd    = latestOf('hours_per_day');
                if (latestDaily  !== null) this.editingEmployee.daily_rate    = latestDaily;
                if (latestHourly !== null) this.editingEmployee.hourly_rate   = latestHourly;
                if (latestHpd    !== null) this.editingEmployee.hours_per_day = latestHpd;
            } catch (e) {
                console.error('Failed to load rates', e);
            }
        },

        toggleMore(type) {
            if (!this.editingEmployee)          this.editingEmployee          = {};
            if (!this.editingEmployee.rateVisible) this.editingEmployee.rateVisible = {};
            if (!this.editingEmployee.ratesByType) this.editingEmployee.ratesByType = {};
            const total = (this.editingEmployee.ratesByType[type] || []).length;
            this.editingEmployee.rateVisible[type] = total;
        },
    };
}
