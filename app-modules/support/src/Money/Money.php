<?php

namespace CorvMC\Support\Money;

use Brick\Money\Money as BrickMoney;
use Stringable;

/**
 * Extended Money class that formats to USD when stringified.
 * Since Brick\Money is final, we use composition and magic methods.
 */
class Money implements Stringable
{
    protected BrickMoney $money;
    
    protected string $locale = 'en_US';
    
    protected bool $includeSymbol = true;
    
    public function __construct(BrickMoney $money)
    {
        $this->money = $money;
    }
    
    /**
     * Create from a Brick Money instance.
     */
    public static function from(BrickMoney $money): self
    {
        return new self($money);
    }
    
    /**
     * Create Money from minor units (cents).
     */
    public static function ofMinor(int $minorAmount, string $currency = 'USD'): self
    {
        return new self(BrickMoney::ofMinor($minorAmount, $currency));
    }
    
    /**
     * Create Money from major units (dollars).
     */
    public static function of($amount, string $currency = 'USD'): self
    {
        return new self(BrickMoney::of($amount, $currency));
    }
    
    /**
     * Format the money when cast to string.
     */
    public function __toString(): string
    {
        return $this->formatTo($this->locale, $this->includeSymbol);
    }
    
    /**
     * Format the money to a specific locale.
     */
    public function formatTo(string $locale, bool $includeSymbol = true): string
    {
        $formatted = $this->money->formatTo($locale);
        
        if (!$includeSymbol) {
            // Remove currency symbol and spaces
            $formatted = preg_replace('/[^\d,.-]/', '', $formatted);
            $formatted = trim($formatted);
        }
        
        return $formatted;
    }
    
    /**
     * Set the default locale for formatting.
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }
    
    /**
     * Set whether to include the currency symbol.
     */
    public function setIncludeSymbol(bool $include): self
    {
        $this->includeSymbol = $include;
        return $this;
    }
    
    /**
     * Get the underlying Brick Money object.
     */
    public function toBrickMoney(): BrickMoney
    {
        return $this->money;
    }
    
    /**
     * Proxy all method calls to the underlying Money object.
     * If the result is a Money object, wrap it in our class.
     */
    public function __call(string $method, array $arguments)
    {
        $result = $this->money->$method(...$arguments);
        
        // If the result is a BrickMoney object, wrap it
        if ($result instanceof BrickMoney) {
            return new self($result);
        }
        
        return $result;
    }
    
    /**
     * Proxy property access to the underlying Money object.
     */
    public function __get(string $property)
    {
        return $this->money->$property;
    }
    
    /**
     * Allow checking if methods exist on the underlying Money object.
     */
    public function __isset(string $property): bool
    {
        return isset($this->money->$property);
    }
    
    /**
     * Check if this money is positive.
     */
    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }
    
    /**
     * Check if this money is negative.
     */
    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }
    
    /**
     * Check if this money is zero.
     */
    public function isZero(): bool
    {
        return $this->money->isZero();
    }
    
    /**
     * Get the amount as cents (minor units).
     */
    public function getMinorAmount(): int
    {
        $result = $this->money->getMinorAmount();
        
        // Convert BigInteger/BigDecimal to int
        if (is_object($result) && method_exists($result, 'toInt')) {
            return $result->toInt();
        }
        
        return (int) $result;
    }
    
    /**
     * Get the currency.
     */
    public function getCurrency()
    {
        return $this->money->getCurrency();
    }
}