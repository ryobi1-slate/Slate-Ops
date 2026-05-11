# Slate Ops Resource Hub - Source of Truth

## Purpose

The Resource Hub is the Slate Ops knowledge base for operational and sales-support reference material.

It serves:

- Techs
- CS
- Slate sales
- Dealer sales

## Scope

The Resource Hub stores vendor documents, Slate-authored references, install notes, QC references, certification packets, SDS documents, sales guides, and dealer enablement resources.

It is not a checkout, ordering, payment, shipping, or retail help-center workflow.

## Ownership

Slate Ops owns the Resource Hub data layer.

Resource records live in:

```text
wp_slate_ops_resources
```

Files may be represented by metadata in MVP. WordPress media-library attachment handling can be added later without changing the resource-review model.

## Review Model

Vendor documents enter the Resource Hub as:

```text
needs_review
```

Slate-authored references can be saved as:

```text
draft
reviewed
```

Only Supervisor/Admin users can create, review, and publish resources.

Published resources use:

```text
reviewed
current
```

## Audience Visibility

Resources carry audience tags:

```text
tech
cs
slate_sales
dealer_sales
```

Tech and CS users should only see published/current resources matching their audience. Supervisor/Admin users may see drafts and review queue items.

Dealer-facing visibility must only expose reviewed/current resources tagged for dealer sales.

## Workflow Position

The Resource Hub supports the locked workflow:

```text
Products -> Product Detail -> Quote Builder -> Quote -> Order Request -> Ops
```

It may be linked contextually from Product Detail, Quote Builder, CS, Tech, and Ops views, but it must not alter the workflow ordering.
