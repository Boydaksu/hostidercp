<?php
declare(strict_types=1);
return [
    "CREATE TABLE catalog_addon_policies (addon_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,tax_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,pricing_strategy VARCHAR(24) CHARACTER SET ascii NOT NULL CHECK(pricing_strategy IN ('SOURCE_FX','FIXED_PER_CURRENCY','FIXED_THEN_FX')),source_currency CHAR(3) CHARACTER SET ascii NOT NULL,activation_policy VARCHAR(16) CHARACTER SET ascii NOT NULL CHECK(activation_policy IN ('AUTO','MANUAL')),refund_policy VARCHAR(16) CHARACTER SET ascii NOT NULL CHECK(refund_policy IN ('none','prorated','staff_approval')),sale_status VARCHAR(16) CHARACTER SET ascii NOT NULL CHECK(sale_status IN ('DRAFT','ACTIVE','CLOSED')),FOREIGN KEY(addon_id) REFERENCES catalog_addons(id),FOREIGN KEY(category_id) REFERENCES catalog_categories(id),FOREIGN KEY(tax_id) REFERENCES catalog_tax_rules(id)) ENGINE=InnoDB",
    "ALTER TABLE catalog_promotions ADD item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,ADD FOREIGN KEY(item_id) REFERENCES catalog_items(id)",
];
