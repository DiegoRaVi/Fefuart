export type ApiSuccess<TData> = {
  success: true;
  data: TData;
  meta?: Record<string, unknown>;
};

export type ApiError = {
  success: false;
  error: {
    code: string;
    message: string;
    details?: Record<string, string[]>;
    trace_id?: string;
  };
};

export type SessionUser = {
  id: number;
  name: string;
  email: string;
  role: string;
};
