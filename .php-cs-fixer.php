<?php

declare(strict_types = 1);
putenv('PHP_CS_FIXER_IGNORE_ENV=1');

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['public', '.data']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                                      => true,
        '@Symfony'                                    => true,
        'no_blank_lines_after_phpdoc'                 => true,
        'visibility_required'                         => true,
        'group_import'                                => false,
        'declare_strict_types'                        => true,
        'fully_qualified_strict_types'                => true,
        'single_import_per_statement'                 => true,
        'no_empty_comment'                            => false,
        'no_unused_imports'                           => true,
        'array_indentation'                           => true,
        'statement_indentation'                       => true,
        'method_chaining_indentation'                 => true,
        'no_useless_else'                             => true,
        'mb_str_functions'                            => true,
        'array_push'                                  => true,
        'modernize_types_casting'                     => true,
        //'new_with_parentheses'                        => true,
        'lowercase_keywords'                          => true,
        'ordered_interfaces'                          => true,
        'ordered_traits'                              => true,
        'blank_line_after_namespace'                  => true,
        'blank_line_after_opening_tag'                => true,
        'elseif'                                      => false,
        'encoding'                                    => true,
        'indentation_type'                            => true,
        'no_useless_return'                           => true,
        'ordered_imports'                             => true,
        'ternary_operator_spaces'                     => true,
        'no_extra_blank_lines'                        => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'multiline_whitespace_before_semicolons'      => true,
        'no_singleline_whitespace_before_semicolons'  => true,
        'no_spaces_around_offset'                     => true,
        'ternary_to_null_coalescing'                  => true,
        'whitespace_after_comma_in_array'             => true,
        'trim_array_spaces'                           => true,
        'trailing_comma_in_multiline'                 => true,
        'unary_operator_spaces'                       => true,
        //'single_space_around_construct'               => true,
        'protected_to_private'                        => true,
        'self_accessor'                               => true,
        'self_static_accessor'                        => true,
        'array_syntax'                                => [
            'syntax' => 'short',
        ],
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'types_spaces' => [
            'space'                => 'single',
            'space_multiple_catch' => 'single',
        ],
        'binary_operator_spaces' => [
            'default'   => 'single_space',
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
                '+=' => 'align_single_space_minimal',
                '-=' => 'align_single_space_minimal',
            ],
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_equal_normalize' => [
            'space' => 'single',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
                'continue',
                'do',
                'exit',
                'for',
                'foreach',
                'if',
                'include',
                'include_once',
                'require',
                'require_once',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_abstract',
                'method_public_static',
                'method_public',
                'method_protected_static',
                'method_protected',
                'method_private_static',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],
    ])
    ->setLineEnding("\n")
    ->setFinder($finder);
