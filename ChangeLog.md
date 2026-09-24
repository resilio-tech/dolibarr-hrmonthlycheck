# CHANGELOG HRMONTHLYCHECK FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.0 (unreleased)

### New Features
- Ask every employee on Zulip, on the 15th of each month, to check their HR information in Dolibarr, with a link to confirm it or ask for a change
- Notify HR on Zulip on the 21st with the number of change requests and a link to the answers
- Answers page listing the change requests, the arrivals and departures and the recap of all employees for a month

### Tests
- `HrMonthlyCheckLibTest.php` - period validation and clipping of the unpaid leaves to the month
