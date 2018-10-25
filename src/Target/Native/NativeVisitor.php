<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

use Hoa\Ruler\Model as AST;
use RulerZ\Exception\OperatorNotFoundException;
use RulerZ\Target\GenericVisitor;
use RulerZ\Model;

class NativeVisitor extends GenericVisitor
{
    /**
     * {@inheritdoc}
     */
    public function visitAccess(AST\Bag\Context $element, &$handle = null, $eldnah = null)
    {
        $flattenedArrayDimensions = [
            sprintf('"%s"', $element->getId()),
        ];

        foreach ($element->getDimensions() as $dimension) {
            $flattenedArrayDimensions[] = sprintf('"%s"', $dimension[AST\Bag\Context::ACCESS_VALUE]);
        }

        return sprintf('[%s]', implode(', ', $flattenedArrayDimensions));
    }

    /**
     * {@inheritdoc}
     */
    public function visitOperator(AST\Operator $element, &$handle = null, $eldnah = null)
    {
        $operatorName = $element->getName();

        // the operator does not exist at all, throw an error before doing anything else.
        if (!$this->operators->hasInlineOperator($operatorName) && !$this->operators->hasOperator($operatorName)) {
            throw new OperatorNotFoundException($operatorName, sprintf('Operator "%s" does not exist.', $operatorName));
        }

        // expand the arguments
        $arguments = array_map(function ($argument) use (&$handle, $eldnah) {
            return $argument->accept($this, $handle, $eldnah);
        }, $element->getArguments());

        $arguments[] = '$this';

        // and either inline the operator call
        if ($this->operators->hasInlineOperator($operatorName)) {
            $callable = $this->operators->getInlineOperator($operatorName);

            return new NativeCompileTimeOperator(
                call_user_func_array($callable, $arguments)
            );
        }

        // or defer it.
        return new NativeRuntimeOperator(sprintf('$operators["%s"]', $operatorName), $arguments);
    }

    public function visitParameter(Model\Parameter $element, &$handle = null, $eldnah = null)
    {
    }
}
