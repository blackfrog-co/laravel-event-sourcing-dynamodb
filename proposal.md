
DynamoDB Package - Drop requirement for Integer Id on `StoredEvent` & `StoredEventRepository`?

I've been looking into making an open source DynamoDB driver package for `laravel-event-sourcing`, in advance of a real world fitness data project that could really use the scalability of a serverless approach to event sourcing. I think DynamoDB is a good match for the event sourcing pattern and with it increasingly becoming a first class citizen in Laravel (provided cache driver, Vapor support) it would be great to create an easy way in for people to use it for event sourcing. I think it could also provide a good way forward to those either experiencing, or concerned about, the scalability of the database table approach in this package.

The way the package currently abstracts the Eloquent layer behind interfaces and does not require use of the Eloquent model for `StoredEvent` is great and makes this much easier, however having made a start on implementation one clear blocker to me is the `int` typing around the `$id` attribute on `StoredEvent`. DynamoDB doesn't natively support an incrementing id. I think to make it work the events themselves would need a UUIDv4 identifier, combined with a composite uuid+timestamp sort key to preserve order for replay.

It seems the `int` id plays double duty as a unique id and also sort key to preserve order within the Eloquent implementations of StoredEvents, however is not depended on elsewhere in the package beyond this.

Would you be open to a PR relaxing the typing on the `id` property of `StoredEvent` and the `find()` method on  `StoredEventRepository` in a future version to allow for strings too for those implementing their own event stores? It's a breaking interface change of course so major version worthy, but I can't forsee a big impact or upgrade burden on any end users and it would make the package more extensible.

If it's a no, I totally understand, it would just be good to know as I would instead need to build as a fork or a new project inspired by this one rather than a driver for your package that provides implementations for `SnapshotRepository` and `StoredEventRepository`.

Thanks in advance for your consideration.

Shaun
