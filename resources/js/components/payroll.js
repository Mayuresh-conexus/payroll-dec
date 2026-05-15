/**
 * payroll.js — Alpine.js component for the Weekly Payroll index page.
 */
export function payrollPage() {
    return {
        items:          [],
        totals:         { weeklyAmount: 0, cash: 0, bank: 0 },
        openSettleModal: null,   // index of row whose settle modal is open

        get year()  { return window.payrollConfig?.year  ?? 0; },
        get month() { return window.payrollConfig?.month ?? 0; },

        /* ── helpers ─────────────────────────────────────────────────────── */

        getPayrollWeeksInMonth(year, month) {
            const weeks = new Set();
            let d = new Date(year, month - 1, 1);
            while (d.getDay() !== 1) d.setDate(d.getDate() + 1);
            while (d.getMonth() === month - 1) {
                const iso       = new Date(d);
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
                const weeklyAmount  = Number(row.weekly_amount            || 0);
                const savedCash     = Number(row.cash_amount              || 0);
                const savedBank     = Number(row.bank_amount              || 0);
                const bankFix       = Number(row.bank_transfer_fix_amount || 0);
                const prevBalance   = Number(row.prev_advance_balance     || 0);
                const isSaved       = savedCash > 0 || savedBank !== bankFix;

                let cashAmount, bankAmount, recover;
                if (isSaved) {
                    bankAmount = savedBank;
                    cashAmount = savedCash;
                    const normalCash = Math.max(0, weeklyAmount - bankAmount);
                    recover = Math.max(0, Math.round((normalCash - savedCash) * 100) / 100);
                } else {
                    bankAmount = bankFix;
                    cashAmount = Math.max(0, weeklyAmount - bankFix);
                    recover    = 0;
                }

                return {
                    weekly_amount:        weeklyAmount,
                    cash:                 Math.round(Math.max(0, cashAmount) * 100) / 100,
                    bank:                 Math.round(Math.max(0, bankAmount) * 100) / 100,
                    bank_fix:             bankFix,
                    recover:              recover,
                    employee_id:          row.employee?.id   ?? null,
                    employee_name:        row.employee?.name ?? '',
                    type:                 row.type           ?? null,
                    gross_amount:         Number(row.gross_amount    || 0),
                    overtime_amount:      Number(row.overtime_amount || 0),
                    addons:               row.addons || [],
                    prev_advance_balance: prevBalance,
                };
            });

            this.recalculateTotals();
        },

        /* ── settle modal ────────────────────────────────────────────────── */

        openSettle(index) {
            this.openSettleModal = index;
            setTimeout(() => {
                const input = document.getElementById('settle-input');
                if (input) input.focus();
            }, 50);
        },

        closeSettle() {
            this.openSettleModal = null;
        },

        /* ── advance balance ─────────────────────────────────────────────── */

        advanceBalance(index) {
            const it      = this.items[index];
            const earned  = Number(it.weekly_amount        || 0);
            const bank    = Number(it.bank                 || 0);
            const prev    = Number(it.prev_advance_balance || 0);
            const recover = Number(it.recover              || 0);
            const given   = Math.max(0, bank - earned);
            return Math.round(Math.max(0, prev + given - recover) * 100) / 100;
        },

        maxRecover(index) {
            const it     = this.items[index];
            const earned = Number(it.weekly_amount        || 0);
            const bank   = Number(it.bank                 || 0);
            const prev   = Number(it.prev_advance_balance || 0);
            return Math.round(Math.min(prev, Math.max(0, earned - bank)) * 100) / 100;
        },

        /** Total surplus cash available this week before any recovery (earned − bank). */
        weeklySurplus(index) {
            const it     = this.items[index];
            const earned = Number(it.weekly_amount || 0);
            const bank   = Number(it.bank          || 0);
            return Math.round(Math.max(0, earned - bank) * 100) / 100;
        },

        /** Employee's cash after recovery deduction. */
        cashAfterRecover(index) {
            const surplus = this.weeklySurplus(index);
            const recover = Number(this.items[index].recover || 0);
            return Math.round(Math.max(0, surplus - recover) * 100) / 100;
        },

        /* ── settlement ──────────────────────────────────────────────────── */

        setRecover(index) {
            const it      = this.items[index];
            const bank    = Number(it.bank          || 0);
            const weekly  = Number(it.weekly_amount || 0);
            let   recover = Number(it.recover       || 0);
            if (!isFinite(recover) || recover < 0) recover = 0;
            recover = Math.min(recover, this.maxRecover(index));
            it.recover = Math.round(recover * 100) / 100;
            it.bank    = Math.round(bank * 100) / 100;
            it.cash    = Math.round(Math.max(0, weekly - bank - recover) * 100) / 100;
            this.recalculateTotals();
        },

        fillMaxRecover(index) {
            this.items[index].recover = this.maxRecover(index);
            this.setRecover(index);
        },

        /* ── row calculations ────────────────────────────────────────────── */

        recalcRow(index) {
            const it      = this.items[index];
            const weekly  = Number(it.weekly_amount || 0);
            const bank    = Number(it.bank          || 0);
            let   cash    = Number(it.cash          || 0);
            if (!isFinite(cash) || cash < 0) cash = 0;
            const surplus = Math.max(0, weekly - bank);
            if (cash > surplus) cash = surplus;
            it.cash    = Math.round(cash * 100) / 100;
            it.bank    = Math.round(bank * 100) / 100;
            it.recover = Math.round(Math.max(0, surplus - cash) * 100) / 100;
            this.recalculateTotals();
        },

        updateFromBank(index) {
            const it     = this.items[index];
            const weekly = Number(it.weekly_amount || 0);
            let   bank   = Number(it.bank          || 0);
            if (!isFinite(bank) || bank < 0) bank = 0;
            it.bank    = Math.round(bank * 100) / 100;
            it.cash    = bank > weekly ? 0 : Math.round(Math.max(0, weekly - bank) * 100) / 100;
            it.recover = 0;
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
