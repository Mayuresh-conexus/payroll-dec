/**
 * payroll.js — Alpine.js component for the Weekly Payroll index page.
 *
 * Registered globally as window.payrollPage so Blade can use:
 *   x-data="payrollPage()" x-init="init(window.payrollServerRows)"
 *
 * PHP-controlled values (year, month, saveUrl) are injected by the Blade
 * _script partial via window.payrollConfig before Alpine initialises.
 */
export function payrollPage() {
    return {
        items:  [],
        totals: { weeklyAmount: 0, cash: 0, bank: 0 },

        get year()  { return window.payrollConfig?.year  ?? 0; },
        get month() { return window.payrollConfig?.month ?? 0; },

        /* ── helpers ─────────────────────────────────────────────────────── */

        getPayrollWeeksInMonth(year, month) {
            const weeks = new Set();
            let d = new Date(year, month - 1, 1);
            while (d.getDay() !== 1) d.setDate(d.getDate() + 1);
            while (d.getMonth() === month - 1) {
                const iso      = new Date(d);
                iso.setDate(iso.getDate() + 4 - (iso.getDay() || 7));
                const yearStart = new Date(iso.getFullYear(), 0, 1);
                const weekNo    = Math.ceil((((iso - yearStart) / 86400000) + 1) / 7);
                weeks.add(weekNo);
                d.setDate(d.getDate() + 7);
            }
            return weeks.size || 1;
        },

        formatMoney(v) {
            return Number(v || 0).toFixed(2);
        },

        /* ── lifecycle ───────────────────────────────────────────────────── */

        init(serverRows) {
            if (!Array.isArray(serverRows)) {
                console.error('Payroll init failed: serverRows is not an array', serverRows);
                return;
            }

            this.items = serverRows.map(row => {
                const weeklyAmount  = Number(row.weekly_amount        || 0);
                const savedCash     = Number(row.cash_amount          || 0);
                const savedBank     = Number(row.bank_amount          || 0);
                const prevBalance   = Number(row.prev_advance_balance || 0);
                // isSaved: payroll has been explicitly saved (cash was set, or bank > weekly = advance)
                const isSaved       = savedCash > 0 || savedBank > weeklyAmount;

                let cashAmount, bankAmount;
                if (isSaved) {
                    cashAmount = savedCash;
                    bankAmount = savedBank;
                } else {
                    // Default: bank = fix amount (savedBank), cash = remainder
                    bankAmount = savedBank;
                    cashAmount = Math.max(0, weeklyAmount - bankAmount);
                }

                return {
                    weekly_amount:        weeklyAmount,
                    cash:                 Math.round(Math.max(0, cashAmount) * 100) / 100,
                    bank:                 Math.round(Math.max(0, bankAmount) * 100) / 100,
                    employee_id:          row.employee?.id  ?? null,
                    type:                 row.type          ?? null,
                    gross_amount:         Number(row.gross_amount    || 0),
                    overtime_amount:      Number(row.overtime_amount || 0),
                    addons:               row.addons || [],
                    prev_advance_balance: prevBalance,
                };
            });

            this.recalculateTotals();
        },

        /* ── advance balance ─────────────────────────────────────────────── */

        /**
         * Running advance balance for a row — reactive as bank changes.
         * Formula: max(0, prev_balance + bank - weekly_earned)
         * Positive = employee has an outstanding advance to settle.
         */
        advanceBalance(index) {
            const it     = this.items[index];
            const earned = Number(it.weekly_amount        || 0);
            const bank   = Number(it.bank                 || 0);
            const prev   = Number(it.prev_advance_balance || 0);
            return Math.round(Math.max(0, prev + bank - earned) * 100) / 100;
        },

        /* ── row calculations ────────────────────────────────────────────── */

        recalcRow(index) {
            const it     = this.items[index];
            const weekly = Number(it.weekly_amount || 0);
            let cash     = Number(it.cash || 0);
            if (!isFinite(cash) || cash < 0) cash = 0;
            if (cash > weekly) cash = weekly;
            it.cash = Math.round(cash * 100) / 100;
            // bank = weekly - cash; advance is handled via updateFromBank instead
            it.bank = Math.round(Math.max(0, weekly - it.cash) * 100) / 100;
            this.recalculateTotals();
        },

        updateFromBank(index) {
            const it     = this.items[index];
            const weekly = Number(it.weekly_amount || 0);
            let bank     = Number(it.bank || 0);
            if (!isFinite(bank) || bank < 0) bank = 0;
            // No upper cap — bank > weekly is a valid advance scenario
            it.bank = Math.round(bank * 100) / 100;
            // When bank exceeds earnings: advance given, cash = 0
            it.cash = bank > weekly
                ? 0
                : Math.round((weekly - bank) * 100) / 100;
            this.recalculateTotals();
        },

        recalculateTotals() {
            let weekly = 0, cash = 0, bank = 0;
            for (const it of this.items) {
                weekly += Number(it.weekly_amount || 0);
                cash   += Number(it.cash          || 0);
                bank   += Number(it.bank          || 0);
            }
            this.totals.weeklyAmount = weekly;
            this.totals.cash         = cash;
            this.totals.bank         = bank;
        },
    };
}
