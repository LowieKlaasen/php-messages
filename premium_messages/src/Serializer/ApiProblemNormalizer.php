<?php

namespace App\Serializer;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Symfony only puts the exception message in an error's "detail" when debugging; otherwise it
 * falls back to the bare status text ("Conflict"). HTTP exceptions are thrown deliberately with a
 * client-facing message, so those messages are always shown. Any other exception is unexpected and
 * keeps the generic text, so internals never leak.
 */
#[AsDecorator('serializer.normalizer.problem')]
final class ApiProblemNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly NormalizerInterface $inner,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $problem = $this->inner->normalize($object, $format, $context);

        // Validation errors already carry a detail built from their violations.
        if (!isset($problem['violations'])
            && $object instanceof FlattenException
            && ($context['exception'] ?? null) instanceof HttpExceptionInterface
            && $object->getMessage() !== ''
        ) {
            $problem['detail'] = $object->getMessage();
        }

        return $problem;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->inner instanceof SerializerAwareInterface) {
            $this->inner->setSerializer($serializer);
        }
    }
}
