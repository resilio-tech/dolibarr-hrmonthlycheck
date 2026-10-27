# CHANGELOG HRMONTHLYCHECK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Unreleased

### New Features
- Link to the check page in the employee message, also in test mode where it opens the page of the test recipient
- Work rate field in percent added by the module on the user card, shown as e.g. "90%" or "77,7%", instead of a user extrafield chosen in the setup

### Tests
- `HrMonthlyCheckLibTest.php` - links of the employee message and work rate text

## 1.1.0 (2026-10-21)

### New Features
- Tell the employees in the message that HR reviews the answers on the 21st
- Notify HR with the number of confirmations, change requests and employees without answer
- Test mode sending every message to a test recipient instead of the employees and HR, to run the scheduled jobs by hand
- Use a Zulip bot of type incoming webhook instead of a generic bot
- Show every IBAN of the employee, decrypted when Dolibarr stores it encrypted

### Tests
- `HrMonthlyCheckLibTest.php` - count of the answers by status

## 1.0.0 (2026-10-09)

### New Features
- Ask every employee on Zulip, on the 15th of each month, to check their HR information in Dolibarr, with a link to confirm it or ask for a change
- Notify HR on Zulip on the 21st with the number of change requests and a link to the answers
- Answers page listing the change requests, the arrivals and departures and the recap of all employees for a month

### Tests
- `HrMonthlyCheckLibTest.php` - period validation and clipping of the unpaid leaves to the month
