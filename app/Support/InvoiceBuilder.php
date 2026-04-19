<?php

namespace App\Support;

use App\Models\User;

class InvoiceBuilder
{
    private array $data = [];

    private array $items = [];

    public static function make(): self
    {
        return new self;
    }

    public function externalId(string $id): self
    {
        $this->data['external_id'] = $id;

        return $this;
    }

    public function amount(float $amount): self
    {
        $this->data['amount'] = $amount;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;

        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;

        return $this;
    }

    public function customer(User $user): self
    {
        $this->data['customer'] = [
            'given_names' => $user->name,
            'email' => $user->email,
        ];

        $this->data['customer_notification_preference'] = [
            'invoice_created' => ['email'],
            'invoice_paid' => ['email'],
        ];

        return $this;
    }

    public function successUrl(string $url): self
    {
        $this->data['success_redirect_url'] = $url;

        return $this;
    }

    public function failureUrl(string $url): self
    {
        $this->data['failure_redirect_url'] = $url;

        return $this;
    }

    public function item(string $name, float $price, string $category): self
    {
        $this->items[] = [
            'name' => $name,
            'quantity' => 1,
            'price' => $price,
            'category' => $category,
        ];

        return $this;
    }

    public function toArray(): array
    {
        $payload = $this->data;

        if (! empty($this->items)) {
            $payload['items'] = $this->items;
        }

        return $payload;
    }
}
