<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

exigirLogin();
redirect('talentflow/?origem=pdi');
