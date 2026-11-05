-- Restaurant conversion, Stage 8: polish. A restaurant does not need the customer's birth date.
ALTER TABLE clients MODIFY birth_client DATE NULL DEFAULT NULL;
