<?php
declare(strict_types=1);
return [
    'ALTER TABLE mock_registrar_calls ADD effect_resource_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD effect_term_years INT UNSIGNED NULL',
    "CREATE TRIGGER domain_renewal_binding BEFORE UPDATE ON domain_renewals FOR EACH ROW BEGIN
        IF OLD.domain_id<>NEW.domain_id OR OLD.customer_id<>NEW.customer_id OR OLD.generation<>NEW.generation OR OLD.order_id<>NEW.order_id OR OLD.period_id<>NEW.period_id OR OLD.term_years<>NEW.term_years OR NOT(OLD.operation_id <=> NEW.operation_id) OR (OLD.status='VERIFIED' AND NEW.status<>'VERIFIED') THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable domain renewal binding';
        END IF;
    END",
];
