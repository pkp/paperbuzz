<?php

/**
 * @defgroup plugins_generic_paperbuzz
 */

/**
 * @file plugins/generic/paperbuzz/index.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_paperbuzz
 * @brief Wrapper for Paperbuzz plugin.
 *
 */


require_once('PaperbuzzPlugin.inc.php');

return new PaperbuzzPlugin();

