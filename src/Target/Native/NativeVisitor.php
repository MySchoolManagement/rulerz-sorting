<?php

declare(strict_types=1);

namespace RulerZ\Sorting\Target\Native;

use Hoa\Ruler\Model as AST;
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
            sprintf('["%s"]', $element->getId()),
        ];

        $flattedObjectDimensions = [
            sprintf('get%s()', ucwords($element->getId())),
        ];

        foreach ($element->getDimensions() as $dimension) {
            $flattenedArrayDimensions[] = sprintf('["%s"]', $dimension[AST\Bag\Context::ACCESS_VALUE]);
            $flattedObjectDimensions[] = sprintf('get%s()', ucwords($dimension[AST\Bag\Context::ACCESS_VALUE]));
        }

        $arrayDimensions = implode('', $flattenedArrayDimensions);
        $objectDimensions = implode('->', $flattedObjectDimensions);

        // only supports arrays of arrays or arrays of objects (who's dimensions are also objects)
        return sprintf('function() use ($target, $parameters) {
            usort($target, function($a, $b) use ($parameters) {
                if (is_object($a)) {
                    $valA = $a->%s;
                    $valB = $b->%s;
                } else {
                    $valA = $a%s;
                    $valB = $b%s;
                }

                if ((true === is_numeric($valA)) || (true === is_numeric($valB))) {
                    $result = ($valA > $valB) ? 1 : -1;
                } else {
                    $result = strcmp($valA, $valB);
                }

                return ($parameters[0] == \RulerZ\Sorting\SortDirection::ASCENDING) ? $result : -$result;
            });

            return $target;
        }', $objectDimensions, $objectDimensions, $arrayDimensions, $arrayDimensions);
    }

    public function visitParameter(Model\Parameter $element, &$handle = null, $eldnah = null)
    {
    }
}
