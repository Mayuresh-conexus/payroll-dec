import './bootstrap';

import Alpine from 'alpinejs';
import { employeesData } from './components/employees.js';
import { payrollPage }   from './components/payroll.js';

/* Expose Alpine components globally so Blade x-data="..." can reference them */
window.employeesData = employeesData;
window.payrollPage   = payrollPage;

window.Alpine = Alpine;
Alpine.start();
