/**
 * payroll.js — Alpine.js component for the Weekly Payroll index page.
 */
export function payrollPage() {
    return {
        items:          [],
        totals:         { weeklyAmount: 0, cash: 0, bank: 0, bhAmount: 0, bhBank: 0 },
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

        /**
         * Everything the employee earned this week — the weekly total plus any
         * bank-holiday cash share settling in it.
         *
         * The weekly total deliberately excludes that share so the column reads
         * the same in a settlement week as in any other, but the cash handed
         * over and every advance figure are measured against the whole amount.
         * Mirror of PayrollService::computeAdvance.
         */
        earningsOf(it) {
            return Number(it.weekly_amount || 0) + Number(it.bh_cash || 0);
        },

        earnings(index) {
            return this.earningsOf(this.items[index]);
        },

        /**
         * What actually gets paid out this week: everything earned, less any
         * advance being recovered. Recovery is withheld rather than moved, so it
         * shrinks the pot that cash and bank divide between them.
         */
        payableOf(it) {
            return Math.max(0, this.earningsOf(it) - Number(it.recover || 0));
        },

        /**
         * The least cash this employee can be handed.
         *
         * The bank-holiday cash share is not discretionary — bh_bank_percent has
         * already decided how the premium splits, so paying less than the cash
         * half in cash would quietly rewrite that split. Capped at what is
         * payable so a large recovery cannot demand an impossible minimum.
         */
        cashFloorOf(it) {
            return Math.min(Number(it.bh_cash || 0), this.payableOf(it));
        },

        cashFloor(index) {
            return this.cashFloorOf(this.items[index]);
        },

        /**
         * Enter moves down the same column, Shift+Enter back up, so a column can
         * be typed straight through without reaching for the mouse.
         *
         * Enter is intercepted rather than left alone because this table is one
         * big form — the default would submit the payroll halfway down it. The
         * arrow keys are deliberately left to the browser, where they still step
         * the number as usual.
         */
        focusSiblingRow(event, direction) {
            const el  = event.target;
            const col = el.dataset.col;
            const row = Number(el.dataset.row);

            if (!col || !Number.isFinite(row)) return;

            const next = document.querySelector(`[data-col="${col}"][data-row="${row + direction}"]`);
            if (!next) return;

            next.focus();
            next.select?.();
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
                const bhAmount      = Number(row.bh_amount                || 0);
                const bhCash        = Number(row.bh_cash                  || 0);
                const bhBank        = Number(row.bh_bank                  || 0);

                // Mirror of PayrollService::mergeWithPayrollRun. The bank-holiday
                // bank share is a separate transfer, so bank stays the fixed figure
                // and this test is unaffected by it.
                const isSaved       = savedCash > 0 || Math.abs(savedBank - bankFix) > 0.005;

                // The cash share settles on top of the weekly total, so it is
                // part of what gets split into cash and bank.
                const earnings      = weeklyAmount + bhCash;

                let cashAmount, bankAmount, recover;
                if (isSaved) {
                    bankAmount = savedBank;
                    cashAmount = savedCash;
                    const normalCash = Math.max(0, earnings - bankAmount);
                    recover = Math.max(0, Math.round((normalCash - savedCash) * 100) / 100);
                } else {
                    bankAmount = bankFix;
                    cashAmount = Math.max(0, earnings - bankFix);
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
                    bh_amount:            bhAmount,
                    bh_cash:              bhCash,
                    bh_bank:              bhBank,
                    bh_amount_derived:    Number(row.bh_amount_derived ?? bhAmount),
                    bh_amount_override:   row.bh_amount_override ?? null,
                    bh_bank_override:     row.bh_bank_override ?? null,
                    bh_bank_percent:      Number(row.bh_bank_percent || 0),
                    // Set when a total is too small to fund the bank side, so the
                    // row can warn before it is saved rather than after.
                    bh_bank_reduced:      0,
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
            const earned  = this.earningsOf(it);
            const bank    = Number(it.bank                 || 0);
            const prev    = Number(it.prev_advance_balance || 0);
            const recover = Number(it.recover              || 0);
            const given   = Math.max(0, bank - earned);
            return Math.round(Math.max(0, prev + given - recover) * 100) / 100;
        },

        maxRecover(index) {
            const it     = this.items[index];
            const earned = this.earningsOf(it);
            const bank   = Number(it.bank                 || 0);
            const prev   = Number(it.prev_advance_balance || 0);
            return Math.round(Math.min(prev, Math.max(0, earned - bank)) * 100) / 100;
        },

        /** Total surplus cash available this week before any recovery (earned − bank). */
        weeklySurplus(index) {
            const it     = this.items[index];
            const earned = this.earningsOf(it);
            const bank   = Number(it.bank          || 0);
            return Math.round(Math.max(0, earned - bank) * 100) / 100;
        },

        /** Employee's cash after recovery deduction. */
        cashAfterRecover(index) {
            const surplus = this.weeklySurplus(index);
            const recover = Number(this.items[index].recover || 0);
            return Math.round(Math.max(0, surplus - recover) * 100) / 100;
        },

        /* ── bank-holiday split ──────────────────────────────────────────── */

        /**
         * The premium has two inputs — its total and the bank side — and cash is
         * always what is left. Cash is not edited directly: it appears under
         * Weekly Cash, which is where it is actually paid.
         *
         * Mirror of BankHolidayService::splitPremium. The server re-applies all
         * of this on save, so nothing here is load-bearing for correctness; it
         * exists so the figures on screen match what saving will produce.
         */
        applyBhSplit(index) {
            const it      = this.items[index];
            const amount  = Math.max(0, Number(it.bh_amount || 0));
            const percent = Number(it.bh_bank_percent || 0);

            // The percentage applies to what was earned, not to a hand-set total,
            // so raising the total moves cash and leaves the transfer alone.
            const derived = Math.max(0, Number(it.bh_amount_derived || 0));

            let bank = it.bh_bank_override !== null && it.bh_bank_override !== undefined
                ? Number(it.bh_bank_override)
                : Math.round(derived * percent / 100 * 100) / 100;

            if (!isFinite(bank) || bank < 0) bank = 0;

            // Cash cannot go negative, so a total cut below the bank share takes
            // the difference out of bank. The row says so before it is saved.
            it.bh_bank_reduced = bank > amount ? Math.round((bank - amount) * 100) / 100 : 0;
            bank = Math.min(bank, amount);

            it.bh_bank = Math.round(bank * 100) / 100;
            it.bh_cash = Math.round((amount - bank) * 100) / 100;
            this.resettleRow(index);
        },

        /** The total was typed: remember it, then re-divide. */
        updateBhTotal(index) {
            const it = this.items[index];
            let total = Number(it.bh_amount || 0);
            if (!isFinite(total) || total < 0) total = 0;

            it.bh_amount = Math.round(total * 100) / 100;
            it.bh_amount_override = it.bh_amount;
            this.applyBhSplit(index);
        },

        /** The bank side was typed: remember it, then re-divide. */
        updateBhBank(index) {
            const it = this.items[index];
            let bank = Number(it.bh_bank || 0);
            if (!isFinite(bank) || bank < 0) bank = 0;

            it.bh_bank_override = Math.round(bank * 100) / 100;
            this.applyBhSplit(index);
        },

        /** How far a hand-set total moved the premium away from what was earned. */
        bhDelta(index) {
            const it = this.items[index];

            if (it.bh_amount_override === null || it.bh_amount_override === undefined) {
                return 0;
            }

            return Math.round((Number(it.bh_amount || 0) - Number(it.bh_amount_derived || 0)) * 100) / 100;
        },

        /** Whether anything about this row's premium was set by hand. */
        bhOverridden(index) {
            const it = this.items[index];

            return (it.bh_amount_override !== null && it.bh_amount_override !== undefined)
                || (it.bh_bank_override !== null && it.bh_bank_override !== undefined);
        },

        /** Hand the premium back to the holidays worked and the employee's percentage. */
        resetBhSplit(index) {
            const it = this.items[index];

            it.bh_amount_override = null;
            it.bh_bank_override = null;
            it.bh_amount = Math.round(Number(it.bh_amount_derived || 0) * 100) / 100;
            this.applyBhSplit(index);
        },

        /**
         * Re-derive cash after the earnings moved, holding the bank transfer
         * steady — the premium's cash side is paid in cash, not by transfer.
         */
        resettleRow(index) {
            const it      = this.items[index];
            const payable = this.payableOf(it);
            const bank    = Number(it.bank || 0);
            it.cash = bank > payable ? 0 : Math.round(Math.max(0, payable - bank) * 100) / 100;
            this.recalculateTotals();
        },

        /* ── settlement ──────────────────────────────────────────────────── */

        setRecover(index) {
            const it      = this.items[index];
            const bank    = Number(it.bank          || 0);
            const weekly  = this.earningsOf(it);
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

        /**
         * Cash was edited: bank absorbs the difference so the row always adds up.
         *
         * Recovery is left alone — reducing cash here re-routes money to the bank,
         * it does not repay an advance. That is what the settle flow is for.
         */
        recalcRow(index) {
            const it      = this.items[index];
            const payable = this.payableOf(it);
            const bank    = Number(it.bank || 0);

            // An advance week already transfers more than was earned, so there is
            // nothing left for cash to claim and nothing for bank to absorb.
            if (bank > payable) {
                it.cash = 0;
                this.recalculateTotals();
                return;
            }

            const floor = this.cashFloorOf(it);
            let   cash  = Number(it.cash || 0);
            if (!isFinite(cash) || cash < floor) cash = floor;
            if (cash > payable) cash = payable;

            it.cash = Math.round(cash * 100) / 100;
            it.bank = Math.round(Math.max(0, payable - cash) * 100) / 100;
            this.recalculateTotals();
        },

        /**
         * Bank was edited: cash takes what is left. Setting bank above what is
         * payable is how an advance is given, and leaves nothing in cash.
         */
        updateFromBank(index) {
            const it      = this.items[index];
            const payable = this.payableOf(it);
            let   bank    = Number(it.bank || 0);
            if (!isFinite(bank) || bank < 0) bank = 0;
            it.bank = Math.round(bank * 100) / 100;
            it.cash = bank > payable ? 0 : Math.round(Math.max(0, payable - bank) * 100) / 100;
            this.recalculateTotals();
        },

        recalculateTotals() {
            let weekly = 0, cash = 0, bank = 0, bhAmount = 0, bhBank = 0;
            for (const it of this.items) {
                weekly += Number(it.weekly_amount || 0);
                cash   += Number(it.cash          || 0);
                bank   += Number(it.bank          || 0);
                bhAmount += Number(it.bh_amount   || 0);
                bhBank += Number(it.bh_bank       || 0);
            }
            this.totals.weeklyAmount = weekly;
            this.totals.cash         = cash;
            this.totals.bank         = bank;
            this.totals.bhAmount     = bhAmount;
            this.totals.bhBank       = bhBank;
        },
    };
}
