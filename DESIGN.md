---
name: Efficient Economy
colors:
  surface: '#fcf9f8'
  surface-dim: '#dcd9d9'
  surface-bright: '#fcf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f2'
  surface-container: '#f0eded'
  surface-container-high: '#eae7e7'
  surface-container-highest: '#e5e2e1'
  on-surface: '#1c1b1b'
  on-surface-variant: '#4f434f'
  inverse-surface: '#313030'
  inverse-on-surface: '#f3f0ef'
  outline: '#81737f'
  outline-variant: '#d2c2d0'
  surface-tint: '#8a3f98'
  primary: '#621872'
  on-primary: '#ffffff'
  primary-container: '#7d338b'
  on-primary-container: '#f7adff'
  inverse-primary: '#f7adff'
  secondary: '#466800'
  on-secondary: '#ffffff'
  secondary-container: '#baf55b'
  on-secondary-container: '#4b6f00'
  tertiary: '#612d00'
  on-tertiary: '#ffffff'
  tertiary-container: '#844000'
  on-tertiary-container: '#ffb787'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffd6ff'
  primary-fixed-dim: '#f7adff'
  on-primary-fixed: '#350040'
  on-primary-fixed-variant: '#6f257e'
  secondary-fixed: '#baf55b'
  secondary-fixed-dim: '#9fd840'
  on-secondary-fixed: '#121f00'
  on-secondary-fixed-variant: '#344e00'
  tertiary-fixed: '#ffdcc6'
  tertiary-fixed-dim: '#ffb786'
  on-tertiary-fixed: '#311300'
  on-tertiary-fixed-variant: '#723600'
  background: '#fcf9f8'
  on-background: '#1c1b1b'
  surface-variant: '#e5e2e1'
typography:
  display-lg:
    fontFamily: Manrope
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Manrope
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Manrope
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Manrope
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Manrope
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Manrope
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Manrope
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  container-max: 1200px
  gutter: 24px
  margin-desktop: 64px
  margin-mobile: 20px
---

## Brand & Style

The design system is anchored in the concept of "Accessible Precision." It targets a pragmatic demographic looking for financial value without the complexity often found in traditional banking. The visual direction is a fusion of **Corporate Modern** and **Minimalism**, prioritizing clarity and trust above all else.

The interface should feel clinical and organized, utilizing a dominant white background to project transparency. Purple serves as the anchor of authority, while the green and orange highlights are used sparingly to signal growth, savings, and urgent value. The emotional response should be one of relief and confidence—the user should feel that their benefits are organized, secure, and easy to redeem.

## Colors

This design system utilizes a high-clarity light mode palette. The **Primary Purple (#7D338B)** is reserved for main navigational elements, primary buttons, and branding headers. 

**Secondary Green (#86BC25)** is the "Success and Savings" color, used for discount percentages, positive balance indicators, and confirmation states. **Tertiary Orange (#F58220)** is used for high-visibility call-to-actions, limited-time offers, and notifications. 

The background is kept strictly white (#FFFFFF) or off-white (#F8F9FA) to maintain a "clinical" aesthetic that emphasizes content over container. Neutral tones are deep charcoal to ensure high legibility and an expensive, professional feel.

## Typography

We use **Manrope** across all levels to maintain a contemporary, geometric, and balanced look. Its wide apertures and modern proportions reflect the "Efficient" nature of the brand.

Headlines should use tighter letter spacing and heavier weights to create a sense of presence. Body text is optimized for readability with generous line heights. Labels should be set in semi-bold or medium weights to ensure they stand out even at small sizes, particularly for data-heavy sections like benefit lists or transaction histories.

## Layout & Spacing

The design system employs a **12-column fluid grid** for desktop and a **4-column grid** for mobile. We follow an 8px base grid system to ensure mathematical harmony across all components.

Layouts should favor verticality and "stacking" to allow for quick scanning of benefits. Margins are generous to prevent the "clinical" look from becoming "cluttered." Use white space strategically to separate different categories of discounts, ensuring each offer has room to breathe.

## Elevation & Depth

To maintain a clean and professional look, this design system avoids heavy shadows. Instead, it uses **Tonal Layers** and **Low-Contrast Outlines**.

- **Level 0 (Base):** Pure white background.
- **Level 1 (Cards):** Use a 1px border (#E5E7EB) with a very soft, diffused ambient shadow (0px 4px 20px rgba(0,0,0,0.04)) to lift cards off the surface.
- **Level 2 (Modals/Popovers):** Medium diffusion shadow to indicate high priority and focus.

Interactive elements should not feel "squishy" or neomorphic; they should feel crisp and architectural.

## Shapes

The shape language is **Rounded (0.5rem base)**. This strike a balance between the "clinical" professional feel (sharp) and the "approachable" benefit card feel (pill). 

- **Standard Buttons & Inputs:** 8px (0.5rem)
- **Feature Cards & Benefit Blocks:** 16px (1rem)
- **Status Badges & Chips:** Full pill-shape (999px) to contrast against the structured grid of cards.

## Components

### Buttons
Primary buttons use the Brand Purple with white text. Secondary buttons use a subtle gray background with purple text. Highlight actions (like "Redeem Now") can use the Tertiary Orange to drive conversion.

### Cards
Cards are the primary vehicle for content. They must have a 1px light gray border and a white background. Discount percentages should be displayed in the Secondary Green in a prominent label at the top right of the card.

### Chips & Badges
Use chips for categories (e.g., "Pharmacy," "Grocery"). These should have a light tinted background of the category color with a high-contrast text label.

### Input Fields
Inputs should be clean with an 8px radius and a 1px border. On focus, the border transitions to Primary Purple with a subtle glow. Label text should always be visible above the field (not floating).

### Lists
Benefit lists should use high-contrast typography for the benefit title and a muted gray for the description, separated by 1px horizontal dividers to maintain the organized, efficient aesthetic.