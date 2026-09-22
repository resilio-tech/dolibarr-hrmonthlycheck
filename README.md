# HR Monthly Check - Dolibarr Module

Monthly check of their HR information by the employees, so HR no longer has to chase the information every month.

## Features

- On the 15th of each month, every active employee receives the recap of their HR information for the month, by email, by Zulip direct message or both:
  work rate, weekly hours, salary, BYOD, address, unpaid leave of the month, start or end date when it falls in the month, IBAN
- The message links to a Dolibarr page where the employee confirms the information or describes the change to make. The employee logs in with their own Dolibarr account and can change their answer at any time
- On the 21st, a message is posted to the HR Zulip stream with the number of change requests and a link to the answers page
- The answers page lists, for the chosen month, the change requests, the arrivals and departures, and the recap of all employees with their answer

---

## Installation

### Prerequisites

- Dolibarr >= 17.0
- PHP >= 7.4
- Leave module enabled in Dolibarr (declared as a module dependency)
- For Zulip: a bot account on the Zulip organization. The employees are reached on Zulip by the email of their Dolibarr user

### Module Installation

1. Copy the `hrmonthlycheck` folder into `htdocs/custom/`
2. Enable the module in **Setup > Modules > Human Resources**

---

## Setup

In **Setup > Modules > HR Monthly Check**:

- **Channel** of the recap sent to the employees: email, Zulip or both. The email is sent from the Dolibarr sender address (`MAIN_MAIL_EMAIL_FROM`)
- **Work rate** and **BYOD**: the user extrafields holding them
- **Unpaid leave types**: the leave types counted as unpaid leave. Only approved leaves are counted
- **Zulip**: site URL, bot email and API key, HR stream and topic. The API key is stored encrypted

The two scheduled jobs are disabled by default. Enable them in **Setup > Scheduled jobs** once the setup is complete:

- `HR monthly check: send the recap to employees`, on the 15th at 08:00
- `HR monthly check: notify HR`, on the 21st at 08:00

An employee is a user flagged as employee, enabled, whose employment dates cover at least one day of the month.

---

## Usage

- Employees: **HRM > My monthly HR check**, or the links of the message
- HR: **HRM > Monthly HR check answers**, with the permission *See the monthly check answers of all employees*

---

## Tests

```bash
phpunit test/phpunit/
```

---

## License

GPLv3 or (at your option) any later version. See file COPYING for more information.
