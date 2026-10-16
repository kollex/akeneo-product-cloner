<?php

namespace Flagbit\Bundle\ProductClonerBundle\Controller;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyVariantInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class AbstractController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{

    abstract protected function getNormalizer() : NormalizerInterface;

    abstract protected function getAttributeRepository() : AttributeRepositoryInterface;

    protected function getAttributeCodeBlacklist() : array
    {
        return [];
    }

    protected function normalizeProduct(EntityWithFamilyVariantInterface $product)
    {
        $normalizedProduct = $this->getNormalizer()->normalize($product, 'external_api');

        // When no attributes are defined (except sku), the normalizer returns a stdObject, which breaks the array operations below
        if (!\is_array($normalizedProduct['values'])) {
            $normalizedProduct['values'] = [];
        }

        // Can be returned as stdObject, which breaks the cloning process as an array is expected
        if (!\is_array($normalizedProduct['quantified_associations'])) {
            $normalizedProduct['quantified_associations'] = [];
        }

        while ($parent = $product->getParent()) {
            foreach ($parent->getValuesForVariation() as $value) {
                //this workaround removes the attributes of all parent models, as the getValues() Method,
                // which is called by the normalizer, returns all Values including the values of the parent Model
                unset($normalizedProduct['values'][$value->getAttributeCode()]);
            }
            $product = $parent;
        }

        $ignoredAttributeCodes = array_merge(
            $this->getAttributeRepository()->findUniqueAttributeCodes(),
            $this->getAttributeCodeBlacklist()
        );

        foreach ($ignoredAttributeCodes as $attributeCode) {
            unset($normalizedProduct['values'][$attributeCode]);
        }
        unset($normalizedProduct['identifier']);
        if (empty((array)$normalizedProduct['associations'])) {
            unset($normalizedProduct['associations']);
        }

        return $normalizedProduct;
    }
}
