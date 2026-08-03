# StockFlow UI patterns

## Page composition

- Wrapped pages pass their localized title to `AppLayout` or `AuthLayout`; the
  layout owns `<Head>`. Standalone public and print pages own their document
  title directly.
- Use `PageHeader` for the standard title, optional subtitle, context, and
  right-aligned action composition. Keep subordinate navigation in `before` or
  immediately above the header.
- Create, edit, and settings forms use `mx-auto w-full max-w-3xl`.

## Controls and state

- Use `SearchFilter` for ordinary search fields and keep its localized label
  visible. Related filters belong in one toolbar below the page header.
- Use `FieldError` for field validation and `Alert variant="error"` for form-
  level conflicts or failures.
- Use `Button :loading="form.processing"` for submissions and `DataTable
:loading="filtering"` for table reloads. Both expose accessible busy state and
  reuse the shared loading treatment.
- `EmptyState` never invents copy. Every context that needs supporting text
  supplies an explicit localized description.

## Navigation and actions

- Use `Tabs` for segmented or underline tab sets. Tabs use `v-model`, a
  localized group label, and support arrow, Home, and End keyboard navigation.
- Use `DropdownMenuSeparator` before destructive menu actions.
- Use `Button size="icon-sm"` for 32 px icon actions and `size="icon"` for the
  standard 40 px touch target.
- Permanent deletion uses danger treatment and explicit irreversible copy;
  recoverable trash actions remain visually distinct.

## Containers

- Use `Modal size="sm|md|lg|full"`; do not override modal maximum width with
  ad-hoc classes. Height and overflow classes may remain content-specific.
- Use `Badge` for compact semantic status or category labels. Specialized
  domain badges such as movement types retain their dedicated components.
