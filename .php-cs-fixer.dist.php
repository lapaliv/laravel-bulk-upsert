<?php

ini_set('memory_limit', '256M');

$finder = PhpCsFixer\Finder::create()
    ->exclude(['vendor', 'bootstrap', 'docker', 'public', 'storage', 'swagger'])
    ->in(__DIR__);

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@Symfony' => true,
    '@PSR2' => true,
    '@PhpCsFixer' => true,
    'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
    'concat_space' => ['spacing' => 'one'],
    'yoda_style' => false,
    'no_superfluous_phpdoc_tags' => false,
    'blank_line_before_statement' => ['statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try', 'while', 'switch', 'if', 'for', 'foreach', 'do']],
    'phpdoc_to_comment' => false,
    'phpdoc_no_alias_tag' => ['replacements' => ['type' => 'var', 'link' => 'see']],
    'lambda_not_used_import' => true,
    'global_namespace_import' => [
        'import_constants' => true,
        'import_functions' => true,
        'import_classes' => true,
    ],
    'trailing_comma_in_multiline' => true,
    'switch_case_space' => true,
    'simplified_if_return' => true,
    'no_useless_else' => true,
    'method_argument_space' => true,
    'function_typehint_space' => true,
    'combine_consecutive_issets' => true,
    'declare_parentheses' => true,
    'clean_namespace' => true,
    'blank_line_after_namespace' => true,
    'single_blank_line_before_namespace' => true,
    'no_space_around_double_colon' => true,
    'no_useless_concat_operator' => true,
    'no_useless_nullsafe_operator' => true,
    'not_operator_with_successor_space' => false,
    'ternary_operator_spaces' => true,
    'ternary_to_null_coalescing' => true,
    'align_multiline_comment' => true,
    'general_phpdoc_tag_rename' => true,
    'no_empty_phpdoc' => true,
    'phpdoc_add_missing_param_annotation' => true,
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_indent' => true,
    'phpdoc_no_package' => true,
    'phpdoc_no_empty_return' => false,
    'phpdoc_no_useless_inheritdoc' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => [
        'groups' => [
            ['deprecated'],
            ['property', 'param'],
            ['property-read', 'property-write'],
            ['throws'],
            ['return'],
        ],
    ],
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_trim' => true,
    'phpdoc_var_without_name' => true,
    'no_empty_comment' => false,
    'single_class_element_per_statement' => true,
    'visibility_required' => true,
    'no_blank_lines_after_class_opening' => true,
    'control_structure_continuation_position' => true,
    'phpdoc_types_order' => [
        'null_adjustment' => 'always_last',
    ],
])
    ->setFinder($finder);
