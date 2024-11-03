# The PHP templating engine that speaks your language.

```
composer require tempest/view
```

```html
<x-base title="Home">
    <x-post :foreach="$this->posts as $post">
        {!! $post->title !!}

        <span :if="$this->showDate($post)">
            {{ $post->date }}
        </span>
        <span :else>
            -
        </span>
    </x-post>
    <div :forelse>
        <p>It's quite empty here…</p>
    </div>
    
    <x-footer />
</x-base>
```

[Get started here](https://tempestphp.com/view)

[Join the Tempest Discord](https://discord.gg/pPhpTGUMPQ)