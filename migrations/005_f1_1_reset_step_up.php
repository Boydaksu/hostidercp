<?php
declare(strict_types=1);
// Old verified_at values may have originated from the vulnerable MFA activation path.
return ['UPDATE identity_sessions SET verified_at=NULL WHERE verified_at IS NOT NULL'];
