<?php

namespace {
	if (!isset($GLOBALS['__wp_options']) || !is_array($GLOBALS['__wp_options'])) {
		$GLOBALS['__wp_options'] = [];
	}

	if (!function_exists('get_option')) {
		function get_option(string $key, $default = false) {
			return array_key_exists($key, $GLOBALS['__wp_options']) ? $GLOBALS['__wp_options'][$key] : $default;
		}
	}

	if (!function_exists('update_option')) {
		function update_option(string $key, $value, bool $autoload = false): bool {
			$GLOBALS['__wp_options'][$key] = $value;
			return true;
		}
	}

	if (!function_exists('delete_option')) {
		function delete_option(string $key): bool {
			unset($GLOBALS['__wp_options'][$key]);
			return true;
		}
	}

	if (!isset($GLOBALS['__suite_settings']) || !is_array($GLOBALS['__suite_settings'])) {
		$GLOBALS['__suite_settings'] = [];
	}
	if (!isset($GLOBALS['__suite_throw_delete'])) {
		$GLOBALS['__suite_throw_delete'] = false;
	}

	if (!class_exists('Administration')) {
		class Administration {
			/** @var array<string,mixed> */
			public $settings = [];

			public function retrieveSettings(string $category, bool $clean = true): void {
				$this->settings = $GLOBALS['__suite_settings'][$category] ?? [];
			}

			public function saveSetting(string $category, string $key, $value): void {
				$full = $category . '_' . $key;
				if (!isset($GLOBALS['__suite_settings'][$category]) || !is_array($GLOBALS['__suite_settings'][$category])) {
					$GLOBALS['__suite_settings'][$category] = [];
				}
				$GLOBALS['__suite_settings'][$category][$full] = $value;
			}

			public function deleteSetting(string $category, string $key): void {
				if (!empty($GLOBALS['__suite_throw_delete'])) {
					throw new \RuntimeException('Forced delete failure');
				}
				$full = $category . '_' . $key;
				unset($GLOBALS['__suite_settings'][$category][$full]);
			}
		}
	}

	if (!isset($GLOBALS['__fa_prefs']) || !is_array($GLOBALS['__fa_prefs'])) {
		$GLOBALS['__fa_prefs'] = [];
	}

	if (!function_exists('get_company_prefs')) {
		function get_company_prefs(): array {
			return $GLOBALS['__fa_prefs'];
		}
	}

	if (!function_exists('get_company_pref')) {
		function get_company_pref(string $key) {
			return array_key_exists($key, $GLOBALS['__fa_prefs']) ? $GLOBALS['__fa_prefs'][$key] : null;
		}
	}

	if (!function_exists('set_company_pref')) {
		function set_company_pref(string $key, $value): void {
			$GLOBALS['__fa_prefs'][$key] = $value;
		}
	}

	if (!function_exists('update_company_prefs')) {
		function update_company_prefs(array $prefs): void {
			foreach ($prefs as $k => $v) {
				$GLOBALS['__fa_prefs'][(string)$k] = $v;
			}
		}
	}

	if (!isset($GLOBALS['__fa_db'])) {
		$GLOBALS['__fa_db'] = [
			'tables' => [],
			'last_query_result' => [],
		];
	}

	if (!defined('TB_PREF')) {
		define('TB_PREF', '0_');
	}

	if (!function_exists('db_escape')) {
		function db_escape(string $value): string {
			return addslashes($value);
		}
	}

	if (!function_exists('db_query')) {
		function db_query(string $sql) {
			// Very small SQL subset for tests.
			$GLOBALS['__fa_db']['last_sql'] = $sql;
			return $sql;
		}
	}

	if (!function_exists('db_fetch')) {
		function db_fetch($res) {
			// We interpret $res as SQL and operate on $GLOBALS['__fa_db']['tables'].
			$sql = (string)$res;
			$tbl = null;
			if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/', $sql, $m)) {
				$tbl = $m[1];
			}
			if ($tbl === null) {
				return false;
			}

			// Lazily build a result set for SELECT queries.
			if (!isset($GLOBALS['__fa_db']['result_set'])) {
				$GLOBALS['__fa_db']['result_set'] = [];
			}

			if (stripos($sql, 'SELECT') === 0) {
				if (!isset($GLOBALS['__fa_db']['result_set'][$sql])) {
					$rows = [];
					$table = $GLOBALS['__fa_db']['tables'][$tbl] ?? [];

					if (preg_match("/WHERE\s+[^=]+\s*=\s*'([^']*)'/", $sql, $wm)) {
						$k = stripslashes($wm[1]);
						foreach ($table as $row) {
							if (($row['pref_name'] ?? null) === $k) {
								$rows[] = $row;
							}
						}
					} elseif (preg_match("/LIKE\s*'([^']*)'/", $sql, $lm)) {
						$like = stripslashes($lm[1]);
						$prefix = rtrim($like, '%');
						foreach ($table as $row) {
							$name = (string)($row['pref_name'] ?? '');
							if (strncmp($name, $prefix, strlen($prefix)) === 0) {
								$rows[] = $row;
							}
						}
					} else {
						$rows = array_values($table);
					}

					$GLOBALS['__fa_db']['result_set'][$sql] = $rows;
					$GLOBALS['__fa_db']['result_pos'][$sql] = 0;
				}

				$pos = $GLOBALS['__fa_db']['result_pos'][$sql] ?? 0;
				$rows = $GLOBALS['__fa_db']['result_set'][$sql] ?? [];
				if ($pos >= count($rows)) {
					return false;
				}
				$GLOBALS['__fa_db']['result_pos'][$sql] = $pos + 1;
				return $rows[$pos];
			}

			return false;
		}
	}
}
