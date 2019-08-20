# Use a custom adapter

In order to add a custom adapter, you can provide a factory class to parse/validate your adapter's configs.

```yml
oneup_flysystem:
    adapters:
        custom_adapter:
            custom_adapter_name: #this value should match the key defined in the factory class
                factory: FullyQualified\FactoryClass\Path #or a @serviceId (note the prefix @)
                some_custom_config_1: ~
                some_custom_config_2: ~
```

Or you can create a service implementing the League\Flysystem\AdapterInterface
Set this service as the value of the `service` key in the `oneup_flysystem` configuration.

```yml
oneup_flysystem:
    adapters:
        acme.flysystem_adapter:
            custom:
                service: my_flysystem_service
```

## More to know
* [Create and use your filesystem](filesystem_create.md)
* [Add a cache](filesystem_cache.md)
