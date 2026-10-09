<?php

namespace Zephir\Optimizers\FunctionCall;

use Zephir\Call;
use Zephir\CompilationContext;
use Zephir\CompiledExpression;
use Zephir\Exception\CompilerException;
use Zephir\Optimizers\OptimizerAbstract;

class SocketStreamOptimizer extends OptimizerAbstract
{
    public function optimize(array $expression, Call $call, CompilationContext $context)
    {
        if (!isset($expression['parameters']) || count($expression['parameters']) !== 1) {
            throw new CompilerException("'socket_stream' requires exactly one parameter", $expression);
        }

        $call->processExpectedReturn($context);

        $symbolVariable = $call->getSymbolVariable(true, $context);

        $symbolVariable->setDynamicTypes(['resource']);

        $resolvedParams = $call->getReadOnlyResolvedParams(
            $expression['parameters'],
            $context,
            $expression
        );

        $this->checkInitSymbolVariable($call, $symbolVariable, $context);

        $symbol = $context->backend->getVariableCode($symbolVariable);

        $context->headersManager->add('src/multi-api');

        $context->codePrinter->output(
            'pcurl_socket_stream(' . $symbol . ', '
            . $resolvedParams[0]
            . ');'
        );

        return new CompiledExpression('variable', $symbolVariable->getRealName(), $expression);
    }
}
