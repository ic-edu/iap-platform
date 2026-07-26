# Pricing Architecture & Calculation Rules — iC.edu Assessment Platform (IAP)

## Overview
All financial calculations use `PricingEngine.php` as the single source of truth.

## Formula
$$\text{Base Price} = \text{Product Unit Price} \times \text{Quantity}$$
$$\text{Discount} = \text{Percentage Discount} \lor \text{Fixed Discount}$$
$$\text{Taxable Amount} = \max(0, \text{Base Price} - \text{Discount})$$
$$\text{Grand Total} = \text{Taxable Amount} + \text{Tax}$$
