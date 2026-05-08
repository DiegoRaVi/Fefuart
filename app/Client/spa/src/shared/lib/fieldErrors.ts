import { isApiRequestError } from '../api/client';

export type FieldErrors = Record<string, string>;

export function flattenValidationDetails(details?: Record<string, string[]>): FieldErrors {
  if (!details) {
    return {};
  }

  return Object.entries(details).reduce<FieldErrors>((accumulator, [field, messages]) => {
    if (messages.length > 0) {
      accumulator[field] = messages[0];
    }

    return accumulator;
  }, {});
}

export function getApiFieldErrors(error: unknown): FieldErrors {
  if (!isApiRequestError(error)) {
    return {};
  }

  return flattenValidationDetails(error.details);
}

export function pickFieldErrors<TField extends string>(
  fieldErrors: FieldErrors,
  keys: readonly TField[]
): Partial<Record<TField, string>> {
  return keys.reduce<Partial<Record<TField, string>>>((accumulator, key) => {
    if (fieldErrors[key]) {
      accumulator[key] = fieldErrors[key];
    }

    return accumulator;
  }, {});
}
