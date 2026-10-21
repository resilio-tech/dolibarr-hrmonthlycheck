# CHANGELOG HRMONTHLYCHECK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.0 (unreleased)

### New Features
- Ask every employee on Zulip, on the 15th of each month, to check their HR information in Dolibarr, with a link to confirm it or ask for a change
- Notify HR on Zulip on the 21st with the number of confirmations, change requests and employees without answer, and a link to the answers
- Answers page listing the change requests, the arrivals and departures and the recap of all employees for a month
- Tell the employees in the message that HR reviews the answers on the 21st
- Test mode sending every message to a test recipient instead of the employees and HR, to run the scheduled jobs by hand
- Use a Zulip bot of type incoming webhook instead of a generic bot
- Show every IBAN of the employee, decrypted when Dolibarr stores it encrypted

### Tests
- `HrMonthlyCheckLibTest.php` - period validation, clipping of the unpaid leaves to the month and count of the answers by status
