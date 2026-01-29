<?php

return (new PhpCsFixer\Config())
   ->setFinder(PhpCsFixer\Finder::create()
        ->in(__DIR__)
        ->exclude('vendor')
        ->exclude('.github')
        ->name('*.php')
        ->ignoreDotFiles(true)
        ->ignoreVCS(true)
    )
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS'                 => true,
        '@PHP82Migration'         => true,
        'align_multiline_comment' => true,
        'binary_operator_spaces'  => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
                '='  => 'align_single_space_minimal',
                '!=' => 'align_single_space_minimal',
                '.=' => 'align_single_space_minimal',
                '+=' => 'align_single_space_minimal',
                '??' => 'align_single_space_minimal',
            ],
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'case',
                'continue',
                'declare',
                'default',
                'exit',
                'goto',
                'include',
                'include_once',
                'phpdoc',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
                'yield',
                'yield_from',
            ],
        ],
        'class_reference_name_casing'    => true,
        'class_attributes_separation'    => ['elements' => ['method' => 'one']],
        'dir_constant'                   => true,
        'include'                        => true,
        'magic_constant_casing'          => true,
        'magic_method_casing'            => true,
        'method_chaining_indentation'    => true,
        'native_function_casing'         => true,
        'native_type_declaration_casing' => true,
        'no_alternative_syntax'          => true,
        'no_blank_lines_after_phpdoc'    => true,
        'no_empty_phpdoc'                => true,
        'no_empty_statement'             => true,
        'no_extra_blank_lines'           => [
            'tokens' => [
                'attribute', 'break', 'case', 'continue',
                'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block',
                'return', 'square_brace_block', 'switch', 'throw', 'use',
            ],
        ],
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_around_offset'                    => true,
        'no_trailing_comma_in_singleline'            => true,
        'no_unneeded_control_parentheses'            => true,
        'no_useless_concat_operator'                 => true,
        'no_useless_else'                            => true,
        'no_unused_imports'                          => true,
        'ordered_types'                              => true,
        'phpdoc_indent'                              => true,
        'phpdoc_order'                               => [
            'order' => [
                'param',
                'return', 'throws',
            ],
        ],
        'phpdoc_no_package'           => true,
        'phpdoc_trim'                 => true,
        'return_assignment'           => true,
        'single_line_comment_spacing' => true,
        'single_quote'                => true,
        'statement_indentation'       => false,
        'trim_array_spaces'           => true,
        'types_spaces'                => true,
        'type_declaration_spaces'     => true,
    ])
    ->setLineEnding("\n");
