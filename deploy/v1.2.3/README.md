If version 1.2.2 is running on the server, execute following commands:
```
ALTER TABLE network_case_payments
  CHANGE COLUMN pay_time pay_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE network_case_payments
  ADD COLUMN created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
  AFTER network_case_id;

UPDATE network_case_payments SET created_at = pay_time WHERE 1=1;
```
