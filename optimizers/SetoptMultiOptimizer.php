<?php

namespace Zephir\Optimizers\FunctionCall;

use Zephir\Call;
use Zephir\CompilationContext;
use Zephir\CompiledExpression;
use Zephir\Exception\CompilerException;
use Zephir\Optimizers\OptimizerAbstract;

class SetoptMultiOptimizer extends OptimizerAbstract
{
    public function optimize(array $expression, Call $call, CompilationContext $context)
    {
        if (!isset($expression['parameters']) || count($expression['parameters']) !== 3) {
            throw new CompilerException("'setopt_multi' requires exactly three parameters", $expression);
        }

        $context->headersManager->add('src/multi-api');

        $resolvedParams = $call->getReadOnlyResolvedParams($expression['parameters'], $context, $expression);

        return new CompiledExpression(
            'int',
            'pcurl_multi_setopt('
            . $resolvedParams[0]
            . ', '
            . $resolvedParams[1]
            . ', '
            . $resolvedParams[2]
            . ')',
            $expression
        );
    }
}
