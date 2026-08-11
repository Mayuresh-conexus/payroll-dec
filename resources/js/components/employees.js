/**
 * employees.js — Alpine.js component for the Employees index page.
 *
 * Registered globally as window.employeesData so Blade can use:
 *   x-data="employeesData()"
 */
export function employeesData() {
    return {
        openCreate: false,
    };
}
