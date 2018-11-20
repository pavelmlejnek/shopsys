# Translations

Translating is a process of extracting locale-specific texts from your application and converting them into target language.

We use standard [Symfony translation](https://symfony.com/doc/current/translation.html) so you can use all standard features.
In this article we describe tools and recommendations for translations.

## Usage

1. Create code that uses translations.
   We don't want to duplicate documentation, so please find more in [Symfony translation documentation](https://symfony.com/doc/current/components/translation/usage.html).

1. Once you have a translation in your code, you have to extract translations by running `php phing dump-translations`.
   This command extracts translations into directory [src/Shopsys/ShopBundle/Resources/translations/](/project-base/src/Shopsys/ShopBundle/Resources/translations/).

1. You'll find new translations in `.po` files and you have to translate these newly extracted translations.
   `.po` files are text files so you can make translations in text editor or you can use specialized software.
   Please read more about `.po` in [documentation](https://docs.transifex.com/formats/gettext).

1. Once you create new translations in `.po` files, the application will use these translation immediately.

## Message ID

Message ID is the string you put into translation function. In case of `{{ 'Cart'|translation }}`, the message ID is `Cart`.

We use the original english form as the ID. So in case of
```twig
{% trans with {'%price%': remainingPriceWithVat|price} %}
    You still have to purchase products for <strong> %price% </strong> for <strong> free </strong> shipping and payment.
{% endtrans %}
```
the message ID is `You still have to purchase products for <strong> %price% </strong> for <strong> free </strong> shipping and payment.`.

We replace multiple spaces in message ID to a single one. So in case of
```
{% trans %}
    Shipping and payment
    <strong>for free!</strong>
{% endtrans %}
```
the message ID is `Shipping and payment <strong>for free!</strong>`.

Never use variables in messages. Extractor is not able to guess what is in the variable. Use placeholders instead.
```diff
$translator->trans(
-    'Thanks to ' . $name
+    'Thanks to %name%',
+    ['%name%' => $name]
);
```

This results in message ID `Thanks to %name%` that can be translated even with different word order, for example `%name%, danke!`.

From time to time we use a speciality for message ID, for example `order [noun]`, `order [verb]` that are both translated as `order`.
We do this because in czech, the noun is translated as `objednávka` and the verb is translated as `objednat`.

## Extracted messages

Messages are extracted from following places

### PHP

```php
$this->translator->trans('Offer in feed');
$this->translator->transChoice('{0} no products|{1} product|]1,Inf[ products');
t('Offer in feed');
tc('{0} no products|{1} product|]1,Inf[ products');
```

### Twig

```twig
{{ 'Add another parameter'|trans }}
{{ '{0} no products|{1} product|]1,Inf[ products'|transchoice(count) }}
{{ 'items added to <a href="/cart">cart</a>'|transHtml }}
{{ '{0} item added to <a href="/cart">cart</a>|]0,Inf[ items added to <a href="/cart">cart</a>'|transchoiceHtml(count) }}
```

`trans` and `transchoice` are standard Symfony translations, `transHtml` and `transchoiceHtml` will be explained later.

### JavaScript

```js
Shopsys.translator.trans('Please enter discount code.');
Shopsys.translator.transChoice('{1}Load next item|]1,Inf[Load next items', loadNextCount);
```

JavaScript translations are extracted and translated during compilation of JavaScript.

## Recommended way of changing translations

**Localized translations**
* just change the translation in `.po` file

**Translations in the code (original texts)**
* change the text in the code
* run `dump-translations`
* translate text again because the message ID changed

But be careful, this is a back compatibility breaking change because the original message ID doesn't exist anymore.

## `transHtml`, `transchoiceHtml`

These translation method can be used only in Twig and are similar to filters `|trans|raw`.
The difference is that `transHtml` and `transchoiceHtml` espace parameters to prevent XSS.

They are safe to use in place where you need HTML in messages together with parameters that are user input.

A usage for example:
```twig
{{ 'You have to <a href="%url%">choose</a> products'|transHtml({ '%url%': url('front_homepage') }) }}
```

*Note: The message is not escaped, so if there is malicious code in `.po` files, it will not be escaped.*

## phing dump-translations

* maybe only a link

## PO files

* where are they
* version them
